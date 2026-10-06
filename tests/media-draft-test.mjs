import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { videoDuration } from '../js/media/video.js';
import { markRaw, reactive } from 'vue';

const deferred = () => {
	let resolve, reject;
	const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
	return { promise, resolve, reject };
};

function fixture(api = {}) {
	const state = reactive({ account: { user: { id: 'owner' } }, media: {}, mediaManagerItems: [] });
	const context = vm.createContext({ state, mediaApi: api, videoDuration, markRaw, AbortController, URL, Modal: {}, Icon: {}, draftId: () => 'draft', postsApi: {}, window: { removeEventListener() {} } });
	vm.runInContext(readFileSync(new URL('../js/media/store.js', import.meta.url), 'utf8').replace(/^import .*;\n/gm, '').replace(/export /g, ''), context);
	const script = readFileSync(new URL('../js/posts/post_create.vue', import.meta.url), 'utf8').split('<script>')[1].split('</script>')[0];
	vm.runInContext(script.replace(/^import .*;\n/gm, '').replace('export default', 'component ='), context);
	const events = [];
	const draft = reactive({ post: null, device: { id: 'location' }, $emit: (...event) => events.push(event) });
	Object.assign(draft, context.component.data.call(draft));
	for (const [name, method] of Object.entries(context.component.methods)) draft[name] = method.bind(draft);
	for (const [name, getter] of Object.entries(context.component.computed)) Object.defineProperty(draft, name, { get: () => getter.call(draft) });
	return { state, context, draft, events };
}

// A repeated discard may return no IDs after a lost response; local uploads must still disappear.
{
	const { state, context, draft, events } = fixture({ discardDraft: async () => ({ ids: [] }) });
	for (const id of ['first', 'second']) {
		context.upsertMedia({ id, postId: 'draft' });
		draft.attachments.push({ key: id, id, status: 'ready' });
	}
	context.upsertMedia({ id: 'keep', postId: 'published' });
	await draft.close();
	assert.deepEqual(Array.from(state.mediaManagerItems, item => item.id), ['keep']);
	assert.deepEqual(Object.keys(state.media), ['keep']);
	assert.equal(events[0][0], 'close');
}

// Even after a failed upload was removed from the form, its draft must be cleaned up.
{
	let calls = 0;
	const { draft } = fixture({ discardDraft: async () => { calls++; return { ids: [] }; } });
	draft.uploadStarted = true;
	await draft.close();
	assert.equal(calls, 1);
}

// A list response started before deletion cannot put deleted images back in either cache.
{
	const publicList = deferred(), managedList = deferred();
	const { state, context } = fixture({ list: () => publicList.promise, manageList: () => managedList.promise });
	context.upsertMedia({ id: 'removed', postId: 'draft' });
	const publicRequest = context.loadMedia(true), managedRequest = context.loadManagedMedia(true);
	context.removeMedia('removed');
	context.upsertMedia({ id: 'new', postId: 'other-draft' });
	publicList.resolve({ media: [{ id: 'removed' }, { id: 'keep' }] });
	managedList.resolve({ media: [{ id: 'removed' }, { id: 'keep' }] });
	await Promise.all([publicRequest, managedRequest]);
	assert.deepEqual(Object.keys(state.media), ['keep', 'new']);
	assert.deepEqual(Array.from(state.mediaManagerItems, item => item.id), ['keep', 'new']);
}

// Switching modals unmounts the composer directly rather than calling its close method.
{
	const cleanup = deferred();
	let calls = 0;
	const { state, context, draft } = fixture({ discardDraft: () => { calls++; return cleanup.promise; } });
	context.upsertMedia({ id: 'upload', postId: 'draft' });
	draft.attachments.push({ key: 'upload', id: 'upload', status: 'ready' });
	context.component.beforeUnmount.call(draft);
	assert.equal(calls, 1);
	cleanup.resolve({ ids: ['upload'] });
	await draft.discardRequest;
	assert.equal(state.mediaManagerItems.length, 0);
	assert.equal(Object.keys(state.media).length, 0);
}

// Successful publication must not discard its images when the composer unmounts.
{
	let calls = 0;
	const { context, draft } = fixture({ discardDraft: async () => { calls++; return { ids: [] }; } });
	draft.saved = true;
	draft.attachments.push({ key: 'upload', id: 'upload', status: 'ready' });
	context.component.beforeUnmount.call(draft);
	assert.equal(calls, 0);
}

// A failed image deletion while uploading remains available for cleanup to retry.
{
	let deletionCalls = 0;
	const upload = deferred();
	const { context, draft } = fixture({ upload: () => upload.promise, delete: async () => {
		if (++deletionCalls === 1) throw new Error('Verbindung unterbrochen');
		return { posts: [] };
	} });
	draft.post = { id: 'published', deviceId: 'location' };
	draft.attachments.push({ key: 'upload', status: 'queued', file: {} });
	const processing = draft.processQueue();
	const closing = draft.close();
	upload.resolve({ id: 'upload' });
	await Promise.all([processing, closing]);
	assert.equal(deletionCalls, 2);
	assert.equal(draft.attachments.length, 0);
	assert.equal(draft.closed, true);
}

// Draw the already-loaded image into the square drag preview without cloning image nodes or controls.
for (const [naturalWidth, naturalHeight, expected] of [[100, 200, [28, 0, 56, 112]], [200, 100, [0, 28, 112, 56]]]) {
	const { context, draft } = fixture();
	let previewRemoved = false, cleanup, appended, dragImage, drawing, previewContent;
	const image = { naturalWidth, naturalHeight };
	const painter = {
		roundRect: (...args) => assert.deepEqual(args, [0, 0, 112, 112, 4]),
		fill() {}, clip() {},
		drawImage: (...args) => { drawing = args; },
	};
	const canvas = {
		getContext: type => { assert.equal(type, '2d'); return painter; },
	};
	const preview = {
		style: {},
		append: element => { previewContent = element; },
		remove: () => { previewRemoved = true; },
	};
	const container = {
		getBoundingClientRect: () => ({ left: 30, top: 40, width: 112, height: 112 }),
		querySelector: selector => { assert.equal(selector, '.upload-preview img'); return image; },
	};
	context.document = {
		createElement: tag => { assert.ok(['div', 'canvas'].includes(tag)); return tag === 'div' ? preview : canvas; },
		body: { append: element => { appended = element; } },
	};
	context.requestAnimationFrame = callback => { cleanup = callback; };
	const dataTransfer = { setData() {}, setDragImage: (...args) => { dragImage = args; } };
	draft.startReordering({ key: 'image' }, { currentTarget: container, clientX: 50, clientY: 70, dataTransfer });
	assert.deepEqual(dragImage, [preview, 20, 30]);
	assert.equal(appended, preview);
	assert.equal(drawing[0], image);
	for (const [index, value] of expected.entries()) assert.ok(Math.abs(drawing[index + 1] - value) < 1e-10);
	assert.equal(painter.fillStyle, '#eee');
	assert.equal(previewContent, canvas);
	assert.equal(canvas.width, 112);
	assert.equal(canvas.height, 112);
	assert.equal(preview.style.width, '112px');
	assert.equal(preview.style.height, '112px');
	assert.equal(previewRemoved, false);
	cleanup();
	assert.equal(previewRemoved, true);
	assert.equal(dataTransfer.effectAllowed, 'move');
	assert.equal(draft.draggedAttachmentKey, 'image');
}

console.log('OK: draft cleanup, modal switching, stale media lists, saved posts, deletion retry, and drag preview');

// A selection is either photos or one video, even when supplied by drag and drop.
{
	const { state, draft } = fixture();
	state.account.user.thingsboardAuthority = 'TENANT_ADMIN';
	draft.addFiles([{ type: 'video/mp4', name: 'video.mp4' }, { type: 'image/jpeg', name: 'photo.jpg' }]);
	assert.equal(draft.attachments.length, 0);
	assert.match(draft.uploadError, /einzelnes Video/);
	draft.addFiles([{ type: 'video/mp4', name: 'one.mp4' }, { type: 'video/mp4', name: 'two.mp4' }]);
	assert.equal(draft.attachments.length, 0);
	draft.attachments.push({ key: 'video', id: 'video', type: 'video', status: 'ready' });
	assert.equal(draft.canAddAttachments, false);
	draft.addFiles([{ type: 'image/jpeg', name: 'photo.jpg' }]);
	assert.match(draft.uploadError, /einzelnes Video/);
	draft.detachAttachment(draft.attachments[0]);
	assert.equal(draft.canAddAttachments, true);
	state.account.user.thingsboardAuthority = 'CUSTOMER_USER';
	state.account.user.wasserkarteRole = 'wassermeister';
	draft.addFiles([{ type: 'video/mp4', name: 'video.mp4' }]);
	assert.equal(draft.attachments.length, 0);
	assert.match(draft.uploadError, /vorbehalten/);
	state.account.user.wasserkarteRole = 'super_wassermeister';
	assert.equal(draft.canUploadVideo, true);
}
console.log('OK: video roles, mixed selections, single-video limit, and removing the final attachment');
