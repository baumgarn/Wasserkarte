import { reactive } from 'vue';
import { state } from '@/state.js';

const DEFAULT_SIDE = 'top';
const DEFAULT_OFFSET = 0;
const DEFAULT_ALIGN_OFFSET = 8;
const DEFAULT_DELAY = 500;
const REENABLE_DELAY = 1000;
const DEFAULT_MAX_WIDTH = 270;
const DEFAULT_ARROW_WIDTH = 10;
const DEFAULT_ARROW_HEIGHT = 8;
const DEFAULT_THEME = 'default';
const DEFAULT_PAD_X = 6;
const DEFAULT_PAD_Y = 4;
const VIEWPORT_PADDING = 4;
const SWITCH_GRACE_MS = 90;
const HIDE_TRANSITION_MS = 120;

const VALID_SIDES = new Set(['top', 'right', 'bottom', 'left']);
const VALID_THEMES = new Set(['default', 'bright']);

export const tooltipState = reactive({
	visible: false,
	ready: false,
	content: '',
	contentHtml: '',
	isHtml: false,
	x: 0,
	y: 0,
	placement: DEFAULT_SIDE,
	align: 'center',
	maxWidth: DEFAULT_MAX_WIDTH,
	arrowX: 0,
	arrowY: 0,
	arrowWidth: DEFAULT_ARROW_WIDTH,
	arrowHeight: DEFAULT_ARROW_HEIGHT,
	theme: DEFAULT_THEME,
	padX: DEFAULT_PAD_X,
	padY: DEFAULT_PAD_Y,
});

let tooltipEl = null;
let activeSourceEl = null;
let activeOptions = null;
let positionRaf = null;
let pendingHideTimer = null;
let hideFinalizeTimer = null;

function clearPendingHide() {
	if (pendingHideTimer) {
		clearTimeout(pendingHideTimer);
		pendingHideTimer = null;
	}
}

function clearHideFinalize() {
	if (hideFinalizeTimer) {
		clearTimeout(hideFinalizeTimer);
		hideFinalizeTimer = null;
	}
}

function scheduleHide(sourceEl = null, delayMs = SWITCH_GRACE_MS) {
	clearPendingHide();
	pendingHideTimer = setTimeout(() => {
		pendingHideTimer = null;
		hideTooltip(sourceEl);
	}, delayMs);
}

function parseNumber(value, fallback) {
	if (value === undefined || value === null || value === '') return fallback;
	const parsed = Number(value);
	return Number.isFinite(parsed) ? parsed : fallback;
}

function parseNullableNumber(value) {
	if (value === undefined || value === null || value === '') return null;
	const parsed = Number(value);
	return Number.isFinite(parsed) ? parsed : null;
}

function parseArrowSize(value) {
	const fallback = {
		width: DEFAULT_ARROW_WIDTH,
		height: DEFAULT_ARROW_HEIGHT,
	};
	if (value === undefined || value === null || value === '') return fallback;

	if (Array.isArray(value)) {
		return {
			width: parseNumber(value[0], fallback.width),
			height: parseNumber(value[1], fallback.height),
		};
	}

	if (typeof value === 'object') {
		return {
			width: parseNumber(firstDefined(value.width, value.w, value.x), fallback.width),
			height: parseNumber(firstDefined(value.height, value.h, value.y), fallback.height),
		};
	}

	if (typeof value === 'number') {
		return {
			width: parseNumber(value, fallback.width),
			height: fallback.height,
		};
	}

	if (typeof value === 'string') {
		const tokens = value.trim().split(/[\s,;x]+/).filter(Boolean);
		if (tokens.length >= 2) {
			return {
				width: parseNumber(tokens[0], fallback.width),
				height: parseNumber(tokens[1], fallback.height),
			};
		}
		if (tokens.length === 1) {
			return {
				width: parseNumber(tokens[0], fallback.width),
				height: fallback.height,
			};
		}
	}

	return fallback;
}

function parseBoolean(value, fallback = false) {
	if (value === undefined || value === null) return fallback;
	if (typeof value === 'boolean') return value;
	if (typeof value === 'number') return value !== 0;
	const normalized = String(value).trim().toLowerCase();
	if (normalized === '') return true;
	if (normalized === 'true' || normalized === '1' || normalized === 'yes' || normalized === 'on') return true;
	if (normalized === 'false' || normalized === '0' || normalized === 'no' || normalized === 'off') return false;
	return fallback;
}

function normalizeSide(side) {
	const normalized = String(side || '').toLowerCase();
	return VALID_SIDES.has(normalized) ? normalized : DEFAULT_SIDE;
}

function normalizeAlign(align, side) {
	const normalized = String(align || '').toLowerCase();
	if (!normalized || normalized === 'center' || normalized === 'middle') return 'center';
	if (side === 'top' || side === 'bottom') {
		if (normalized === 'left' || normalized === 'start' || normalized === 'top') return 'left';
		if (normalized === 'right' || normalized === 'end' || normalized === 'bottom') return 'right';
		return 'center';
	}
	if (normalized === 'top' || normalized === 'start' || normalized === 'left') return 'top';
	if (normalized === 'bottom' || normalized === 'end' || normalized === 'right') return 'bottom';
	return 'center';
}

function normalizeTheme(theme, bright = false) {
	if (parseBoolean(bright, false)) {
		return 'bright';
	}

	const normalized = String(theme || '').trim().toLowerCase();
	return VALID_THEMES.has(normalized) ? normalized : DEFAULT_THEME;
}

function clamp(value, min, max) {
	if (max < min) return min;
	return Math.min(Math.max(value, min), max);
}

function normalizeContent(content) {
	if (content === undefined || content === null) return '';
	return String(content);
}

function isElementNode(value) {
	return Boolean(value && typeof value === 'object' && value.nodeType === 1);
}

function resolveContentRef(value) {
	if (!value) return null;
	if (isElementNode(value)) return value;
	if (typeof document === 'undefined') return null;
	if (typeof value !== 'string') return null;
	const selector = value.trim();
	if (!selector) return null;

	let node = null;
	try {
		node = document.querySelector(selector);
	} catch (err) {
		node = null;
	}
	if (node) return node;

	const id = selector.charAt(0) === '#' ? selector.slice(1) : selector;
	if (!id) return null;
	return document.getElementById(id);
}

function resolveAlignOffset(align, value) {
	const defaultOffset = align === 'center' ? 0 : DEFAULT_ALIGN_OFFSET;
	if (value === undefined || value === null || value === '') return defaultOffset;
	return parseNumber(value, defaultOffset);
}

function resolveArrowCoord(size, align, arrowPosition, defaultCoord, startAlign, endAlign) {
	if (arrowPosition === null) return defaultCoord;
	if (align === startAlign) return arrowPosition;
	if (align === endAlign) return size - arrowPosition;
	return (size / 2) + arrowPosition;
}

function firstDefined() {
	for (let i = 0; i < arguments.length; i += 1) {
		const value = arguments[i];
		if (value !== undefined && value !== null) {
			return value;
		}
	}
	return undefined;
}

function getPropValue(props, keys) {
	if (!props) return undefined;
	for (let i = 0; i < keys.length; i += 1) {
		const key = keys[i];
		if (Object.prototype.hasOwnProperty.call(props, key)) {
			return props[key];
		}
	}
	return undefined;
}

function readDirectiveOptions(el, bindingValue, vnodeProps) {
	const bindingOptions = (bindingValue && typeof bindingValue === 'object' && !Array.isArray(bindingValue)) ? bindingValue : {};
	const directContent = (typeof bindingValue === 'string' || typeof bindingValue === 'number') ? bindingValue : undefined;
	const content = firstDefined(
		directContent,
		bindingOptions.content,
		getPropValue(vnodeProps, ['tooltipcontent', 'tooltipContent']),
		el.getAttribute('tooltipcontent'),
		''
	);
	const contentRef = firstDefined(
		bindingOptions.contentRef,
		bindingOptions.contentref,
		getPropValue(vnodeProps, ['tooltipcontentref', 'tooltipcontentRef']),
		el.getAttribute('tooltipcontentref')
	);
	const side = normalizeSide(firstDefined(
		bindingOptions.side,
		bindingOptions.position,
		getPropValue(vnodeProps, ['tooltipside', 'tooltipSide', 'tooltipposition', 'tooltipPosition']),
		el.getAttribute('tooltipside'),
		el.getAttribute('tooltipposition')
	));
	const align = normalizeAlign(firstDefined(
		bindingOptions.align,
		bindingOptions.alignment,
		getPropValue(vnodeProps, ['tooltipalign', 'tooltipAlign']),
		el.getAttribute('tooltipalign')
	), side);
	const offset = parseNumber(firstDefined(
		bindingOptions.offset,
		getPropValue(vnodeProps, ['tooltipoffset', 'tooltipOffset']),
		el.getAttribute('tooltipoffset')
	), DEFAULT_OFFSET);
	const alignOffset = resolveAlignOffset(align, firstDefined(
		bindingOptions.alignOffset,
		bindingOptions.crossOffset,
		getPropValue(vnodeProps, ['tooltipalignoffset', 'tooltipAlignOffset', 'tooltipcrossoffset', 'tooltipCrossOffset']),
		el.getAttribute('tooltipalignoffset'),
		el.getAttribute('tooltipcrossoffset')
	));
	const arrowPosition = parseNullableNumber(firstDefined(
		bindingOptions.arrowPosition,
		bindingOptions.arrowposition,
		getPropValue(vnodeProps, ['tooltiparrowposition', 'tooltipArrowPosition']),
		el.getAttribute('tooltiparrowposition')
	));
	const arrowSize = parseArrowSize(firstDefined(
		bindingOptions.arrowSize,
		bindingOptions.arrowsize,
		getPropValue(vnodeProps, ['tooltiparrowsize', 'tooltipArrowSize']),
		el.getAttribute('tooltiparrowsize')
	));
	const delay = parseNumber(firstDefined(
		bindingOptions.delay,
		getPropValue(vnodeProps, ['tooltipdelay', 'tooltipDelay']),
		el.getAttribute('tooltipdelay')
	), DEFAULT_DELAY);
	const followCursor = parseBoolean(firstDefined(
		bindingOptions.followCursor,
		bindingOptions.followcursor,
		getPropValue(vnodeProps, ['tooltipfollowcursor', 'tooltipFollowCursor']),
		el.getAttribute('tooltipfollowcursor')
	));
	const maxWidth = parseNumber(
		firstDefined(
			bindingOptions.maxWidth,
			bindingOptions.maxwidth,
			bindingOptions.width,
			getPropValue(vnodeProps, ['tooltipmaxwidth', 'tooltipMaxWidth', 'tooltipwidth', 'tooltipWidth']),
			el.getAttribute('tooltipmaxwidth'),
			el.getAttribute('tooltipwidth')
		),
		DEFAULT_MAX_WIDTH
	);
	const theme = normalizeTheme(
		firstDefined(
			bindingOptions.theme,
			getPropValue(vnodeProps, ['tooltiptheme', 'tooltipTheme']),
			el.getAttribute('tooltiptheme')
		),
		firstDefined(
			bindingOptions.bright,
			getPropValue(vnodeProps, ['tooltipbright', 'tooltipBright']),
			el.getAttribute('tooltipbright')
		)
	);
	const padX = parseNumber(firstDefined(
		bindingOptions.padX,
		bindingOptions.padx,
		getPropValue(vnodeProps, ['tooltippadx', 'tooltipPadX']),
		el.getAttribute('tooltippadx')
	), DEFAULT_PAD_X);
	const padY = parseNumber(firstDefined(
		bindingOptions.padY,
		bindingOptions.pady,
		getPropValue(vnodeProps, ['tooltippady', 'tooltipPadY']),
		el.getAttribute('tooltippady')
	), DEFAULT_PAD_Y);
	const disabled = parseBoolean(firstDefined(
		bindingOptions.disabled,
		getPropValue(vnodeProps, ['tooltipdisabled', 'tooltipDisabled']),
		el.getAttribute('tooltipdisabled')
	));
	const handover = parseBoolean(firstDefined(
		bindingOptions.handover,
		getPropValue(vnodeProps, ['tooltiphandover', 'tooltipHandover']),
		el.getAttribute('tooltiphandover')
	), false);
	const hideOnClick = parseBoolean(firstDefined(
		bindingOptions.hideOnClick,
		bindingOptions.hideonclick,
		getPropValue(vnodeProps, ['tooltiphideonclick', 'tooltipHideOnClick']),
		el.getAttribute('tooltiphideonclick')
	), true);
	const handoverDelay = parseNumber(firstDefined(
		bindingOptions.handoverDelay,
		getPropValue(vnodeProps, ['tooltiphandoverdelay', 'tooltipHandoverDelay']),
		el.getAttribute('tooltiphandoverdelay')
	), SWITCH_GRACE_MS);
	return {
		content: normalizeContent(content),
		contentRef,
		side,
		align,
		offset,
		alignOffset,
		arrowPosition,
		arrowSize,
		delay,
		followCursor,
		maxWidth,
		theme,
		padX,
		padY,
		disabled,
		handover,
		hideOnClick,
		handoverDelay,
		force: parseBoolean(bindingOptions.force, false),
	};
}

function hasConfiguredContent(options) {
	if (options.contentRef !== undefined && options.contentRef !== null && options.contentRef !== '') {
		return true;
	}
	return Boolean(options.content && options.content.trim().length > 0);
}

function getAnchorRect(options) {
	if (options.anchorRect) return options.anchorRect;
	if (options.sourceEl && options.sourceEl.getBoundingClientRect) {
		return options.sourceEl.getBoundingClientRect();
	}
	if (Number.isFinite(options.x) && Number.isFinite(options.y)) {
		const x = options.x;
		const y = options.y;
		return { left: x, right: x, top: y, bottom: y, width: 0, height: 0 };
	}
	return null;
}

function getPositionFromPlacement(anchorRect, placement, align, width, height, offset, alignOffset) {
	const centerX = anchorRect.left + (anchorRect.width || 0) / 2;
	const centerY = anchorRect.top + (anchorRect.height || 0) / 2;
	const leftX = anchorRect.left;
	const rightX = anchorRect.right - width;
	const topY = anchorRect.top;
	const bottomY = anchorRect.bottom - height;
	const horizontalAlignOffset = align === 'right' ? -alignOffset : alignOffset;
	const verticalAlignOffset = align === 'bottom' ? -alignOffset : alignOffset;
	if (placement === 'bottom') {
		return {
			x: (align === 'left' ? leftX : align === 'right' ? rightX : centerX - width / 2) + horizontalAlignOffset,
			y: anchorRect.bottom + offset,
		};
	}
	if (placement === 'left') {
		return {
			x: anchorRect.left - width - offset,
			y: (align === 'top' ? topY : align === 'bottom' ? bottomY : centerY - height / 2) + verticalAlignOffset,
		};
	}
	if (placement === 'right') {
		return {
			x: anchorRect.right + offset,
			y: (align === 'top' ? topY : align === 'bottom' ? bottomY : centerY - height / 2) + verticalAlignOffset,
		};
	}
	return {
		x: (align === 'left' ? leftX : align === 'right' ? rightX : centerX - width / 2) + horizontalAlignOffset,
		y: anchorRect.top - height - offset,
	};
}

function choosePlacement(anchorRect, placement, width, height, offset) {
	if (placement === 'top' && anchorRect.top - height - offset < VIEWPORT_PADDING) return 'bottom';
	if (placement === 'bottom' && anchorRect.bottom + height + offset > window.innerHeight - VIEWPORT_PADDING) return 'top';
	if (placement === 'left' && anchorRect.left - width - offset < VIEWPORT_PADDING) return 'right';
	if (placement === 'right' && anchorRect.right + width + offset > window.innerWidth - VIEWPORT_PADDING) return 'left';
	return placement;
}

function positionTooltip() {
	if (!tooltipEl || !activeOptions) return;
	if (activeOptions.sourceEl && !activeOptions.sourceEl.isConnected) {
		hideTooltip(activeOptions.sourceEl);
		return;
	}

	const width = tooltipEl.offsetWidth || 0;
	const height = tooltipEl.offsetHeight || 0;

	let x = 0;
	let y = 0;
	let placement = activeOptions.side;
	let align = activeOptions.align;
	let anchorRect = null;

	if (activeOptions.followCursor && Number.isFinite(activeOptions.x) && Number.isFinite(activeOptions.y)) {
		x = activeOptions.x + activeOptions.offset;
		y = activeOptions.y + activeOptions.offset;

		if (x + width > window.innerWidth - VIEWPORT_PADDING) {
			x = activeOptions.x - width - activeOptions.offset;
		}
		if (y + height > window.innerHeight - VIEWPORT_PADDING) {
			y = activeOptions.y - height - activeOptions.offset;
		}

		placement = 'cursor';
		align = 'center';
	} else {
		anchorRect = getAnchorRect(activeOptions);
		if (!anchorRect) {
			hideTooltip(activeOptions.sourceEl);
			return;
		}

		const finalPlacement = choosePlacement(anchorRect, placement, width, height, activeOptions.offset);
		const finalAlign = normalizeAlign(align, finalPlacement);
		const pos = getPositionFromPlacement(anchorRect, finalPlacement, finalAlign, width, height, activeOptions.offset, activeOptions.alignOffset);
		x = pos.x;
		y = pos.y;
		placement = finalPlacement;
		align = finalAlign;
	}

	tooltipState.x = Math.round(clamp(x, VIEWPORT_PADDING, window.innerWidth - width - VIEWPORT_PADDING));
	tooltipState.y = Math.round(clamp(y, VIEWPORT_PADDING, window.innerHeight - height - VIEWPORT_PADDING));
	const nextArrowWidth = activeOptions.arrowSize.width;
	const nextArrowHeight = activeOptions.arrowSize.height;
	const layoutChanged = (
		tooltipState.placement !== placement
		|| tooltipState.align !== align
		|| tooltipState.arrowWidth !== nextArrowWidth
		|| tooltipState.arrowHeight !== nextArrowHeight
	);
	tooltipState.placement = placement;
	tooltipState.align = align;
	tooltipState.arrowWidth = nextArrowWidth;
	tooltipState.arrowHeight = nextArrowHeight;
	if (anchorRect && placement !== 'cursor') {
		const centerX = anchorRect.left + (anchorRect.width || 0) / 2;
		const centerY = anchorRect.top + (anchorRect.height || 0) / 2;
		const defaultArrowX = centerX - tooltipState.x;
		const defaultArrowY = centerY - tooltipState.y;
		const halfArrow = Math.max(2, Math.round(activeOptions.arrowSize.width / 2));
		if (placement === 'top' || placement === 'bottom') {
			const arrowX = resolveArrowCoord(width, align, activeOptions.arrowPosition, defaultArrowX, 'left', 'right');
			tooltipState.arrowX = Math.round(clamp(arrowX, halfArrow + 1, width - halfArrow - 1));
			tooltipState.arrowY = Math.round(clamp(defaultArrowY, halfArrow + 1, height - halfArrow - 1));
		} else {
			const arrowY = resolveArrowCoord(height, align, activeOptions.arrowPosition, defaultArrowY, 'top', 'bottom');
			tooltipState.arrowX = Math.round(clamp(defaultArrowX, halfArrow + 1, width - halfArrow - 1));
			tooltipState.arrowY = Math.round(clamp(arrowY, halfArrow + 1, height - halfArrow - 1));
		}
	}
	if (layoutChanged) {
		queuePosition();
	}
}

function queuePosition() {
	if (positionRaf) cancelAnimationFrame(positionRaf);
	positionRaf = requestAnimationFrame(() => {
		positionRaf = null;
		positionTooltip();
	});
}

export function setTooltipElement(el) {
	tooltipEl = el;
	if (tooltipEl && tooltipState.visible) {
		queuePosition();
	}
}

export function getActiveTooltipSource() {
	return activeSourceEl;
}

export function showTooltip(options = {}) {
	clearPendingHide();
	clearHideFinalize();
	const contentRef = resolveContentRef(firstDefined(options.contentRef, options.contentref));
	const contentHtml = contentRef ? contentRef.innerHTML : '';
	const content = normalizeContent(options.content);
	if (!contentHtml && content.trim().length === 0) {
		hideTooltip(options.sourceEl);
		return;
	}
	if (state.tooltips === false && !options.force) {
		hideTooltip(options.sourceEl);
		return;
	}

	const wasVisible = tooltipState.visible;
	activeSourceEl = firstDefined(options.sourceEl, null);
	const side = normalizeSide(firstDefined(options.side, options.position));
	const align = normalizeAlign(firstDefined(options.align, options.alignment), side);
	const theme = normalizeTheme(firstDefined(options.theme, options.tooltipTheme), firstDefined(options.bright, options.tooltipBright));
	activeOptions = {
		content,
		side,
		align,
		offset: parseNumber(options.offset, DEFAULT_OFFSET),
		alignOffset: resolveAlignOffset(align, firstDefined(options.alignOffset, options.crossOffset)),
		arrowPosition: parseNullableNumber(firstDefined(options.arrowPosition, options.arrowposition)),
		arrowSize: parseArrowSize(firstDefined(options.arrowSize, options.arrowsize)),
		followCursor: parseBoolean(options.followCursor),
		maxWidth: parseNumber(firstDefined(options.maxWidth, options.maxwidth, options.width), DEFAULT_MAX_WIDTH),
		theme,
		padX: parseNumber(firstDefined(options.padX, options.padx), DEFAULT_PAD_X),
		padY: parseNumber(firstDefined(options.padY, options.pady), DEFAULT_PAD_Y),
		x: Number.isFinite(options.x) ? options.x : null,
		y: Number.isFinite(options.y) ? options.y : null,
		anchorRect: firstDefined(options.anchorRect, null),
		sourceEl: firstDefined(options.sourceEl, null),
	};

	tooltipState.content = contentHtml ? '' : content;
	tooltipState.contentHtml = contentHtml;
	tooltipState.isHtml = Boolean(contentHtml);
	tooltipState.maxWidth = activeOptions.maxWidth;
	tooltipState.theme = activeOptions.theme;
	tooltipState.padX = activeOptions.padX;
	tooltipState.padY = activeOptions.padY;
	tooltipState.visible = true;
	tooltipState.ready = options.immediate || wasVisible;

	queuePosition();

	if (!tooltipState.ready) {
		requestAnimationFrame(() => {
			if (tooltipState.visible) {
				tooltipState.ready = true;
			}
		});
	}
}

export function hideTooltip(sourceEl = null) {
	clearPendingHide();
	if (sourceEl && activeSourceEl && sourceEl !== activeSourceEl) return;
	if (positionRaf) {
		cancelAnimationFrame(positionRaf);
		positionRaf = null;
	}
	tooltipState.ready = false;
	clearHideFinalize();
	hideFinalizeTimer = setTimeout(() => {
		hideFinalizeTimer = null;
		if (!tooltipState.ready) {
			tooltipState.visible = false;
			activeSourceEl = null;
			activeOptions = null;
		}
	}, HIDE_TRANSITION_MS);
}

function mountDirective(el, binding, vnodeProps) {
	const initialOptions = readDirectiveOptions(el, binding && binding.value, vnodeProps);
	const context = {
		binding,
		vnodeProps,
		showTimer: null,
		lastX: null,
		lastY: null,
		isHovering: false,
		isFocused: false,
		lastDisabled: initialOptions.disabled,
	};

	const clearTimer = () => {
		if (context.showTimer) {
			clearTimeout(context.showTimer);
			context.showTimer = null;
		}
	};

	const updateMouse = (event) => {
		if (event && Number.isFinite(event.clientX) && Number.isFinite(event.clientY)) {
			context.lastX = event.clientX;
			context.lastY = event.clientY;
			return;
		}
		const rect = el.getBoundingClientRect();
		context.lastX = rect.left + rect.width / 2;
		context.lastY = rect.top + rect.height / 2;
	};

	const showNow = (immediate = false) => {
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		if (options.disabled || !hasConfiguredContent(options)) {
			hideTooltip(el);
			return;
		}
		showTooltip({
			content: options.content,
			contentRef: options.contentRef,
			side: options.side,
			align: options.align,
			offset: options.offset,
			alignOffset: options.alignOffset,
			arrowPosition: options.arrowPosition,
			arrowSize: options.arrowSize,
			followCursor: options.followCursor,
			maxWidth: options.maxWidth,
			theme: options.theme,
			padX: options.padX,
			padY: options.padY,
			force: options.force,
			sourceEl: el,
			x: context.lastX,
			y: context.lastY,
			immediate,
		});
	};

	const scheduleShow = (delayOverride = null) => {
		clearTimer();
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		if (options.disabled || !hasConfiguredContent(options)) return;
		const delay = delayOverride === null ? options.delay : delayOverride;
		if (delay <= 0) {
			showNow(false);
			return;
		}
		context.showTimer = setTimeout(() => {
			context.showTimer = null;
			showNow(false);
		}, delay);
	};

	context.onMouseEnter = (event) => {
		context.isHovering = true;
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		context.lastDisabled = options.disabled;
		updateMouse(event);
		if (options.disabled || !hasConfiguredContent(options)) {
			clearPendingHide();
			hideTooltip();
			return;
		}
		const keepWarm = options.handover && tooltipState.visible;
		if (options.handover && pendingHideTimer && !tooltipState.visible) {
			clearPendingHide();
		}
		if (!options.handover && pendingHideTimer) {
			clearPendingHide();
		}
		if (keepWarm) {
			clearPendingHide();
			showNow(true);
			return;
		}
		if (!options.handover && tooltipState.visible && getActiveTooltipSource() !== el) {
			hideTooltip();
		}
		scheduleShow();
	};
	context.onMouseMove = (event) => {
		updateMouse(event);
		if (context.showTimer) return;
		if (getActiveTooltipSource() !== el) return;
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		if (options.disabled || !hasConfiguredContent(options)) return;
		if (options.followCursor) {
			showNow(true);
		}
	};
	context.onMouseLeave = () => {
		context.isHovering = false;
		clearTimer();
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		if (options.handover) {
			scheduleHide(el, options.handoverDelay);
			return;
		}
		hideTooltip(el);
	};
	context.onFocus = (event) => {
		context.isFocused = true;
		clearPendingHide();
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		context.lastDisabled = options.disabled;
		updateMouse(event);
		if (options.disabled || !hasConfiguredContent(options)) {
			hideTooltip();
			return;
		}
		scheduleShow();
	};
	context.onBlur = () => {
		context.isFocused = false;
		clearTimer();
		clearPendingHide();
		hideTooltip(el);
	};
	context.onClick = () => {
		const options = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		if (!options.hideOnClick) return;
		clearTimer();
		clearPendingHide();
		hideTooltip(el);
	};

	el.addEventListener('mouseenter', context.onMouseEnter);
	el.addEventListener('mousemove', context.onMouseMove);
	el.addEventListener('mouseleave', context.onMouseLeave);
	el.addEventListener('focus', context.onFocus, true);
	el.addEventListener('blur', context.onBlur, true);
	el.addEventListener('click', context.onClick, true);

	context.showNow = showNow;
	context.clearTimer = clearTimer;
	context.scheduleShow = scheduleShow;
	el.__appTooltip = context;
}

function unmountDirective(el) {
	const context = el.__appTooltip;
	if (!context) return;

	if (context.clearTimer) {
		context.clearTimer();
	}
	el.removeEventListener('mouseenter', context.onMouseEnter);
	el.removeEventListener('mousemove', context.onMouseMove);
	el.removeEventListener('mouseleave', context.onMouseLeave);
	el.removeEventListener('focus', context.onFocus, true);
	el.removeEventListener('blur', context.onBlur, true);
	el.removeEventListener('click', context.onClick, true);
	hideTooltip(el);
	delete el.__appTooltip;
}

export const tooltipDirective = {
	mounted(el, binding, vnode) {
		mountDirective(el, binding, vnode && vnode.props);
	},
	updated(el, binding, vnode) {
		const context = el.__appTooltip;
		if (!context) return;
		context.binding = binding;
		context.vnodeProps = vnode && vnode.props;
		const currentOptions = readDirectiveOptions(el, context.binding && context.binding.value, context.vnodeProps);
		const isInside = context.isHovering || context.isFocused;
		if (isInside && context.lastDisabled && !currentOptions.disabled) {
			context.scheduleShow(REENABLE_DELAY);
			context.lastDisabled = currentOptions.disabled;
			return;
		}
		context.lastDisabled = currentOptions.disabled;
		if (getActiveTooltipSource() === el) {
			context.showNow(true);
		}
	},
	beforeUnmount(el) {
		unmountDirective(el);
	},
};

if (typeof window !== 'undefined') {
	window.addEventListener('mousemove', (event) => {
		if (!activeOptions || !activeOptions.followCursor) return;
		activeOptions.x = event.clientX;
		activeOptions.y = event.clientY;
		queuePosition();
	});

	const reposition = () => {
		if (!tooltipState.visible) return;
		if (!activeOptions || activeOptions.followCursor) return;
		queuePosition();
	};

	window.addEventListener('resize', reposition);
	window.addEventListener('scroll', reposition, true);
	window.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			hideTooltip();
		}
	});
}
