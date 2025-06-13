import { state } from './state.js';
import { config } from './config.js';

export async function fetchWMSCapabilities(wmsUrls) {
	const results = [];

	// Loop through each WMS URL and fetch the capabilities
	for (let i = 0; i < wmsUrls.length; i++) {
		const parsedData = await parseWMSCapabilities(wmsUrls[i]);
		if (parsedData) {
			results.push(parsedData);
		}
	}

	results.forEach((wmsData) => {
		wmsData.layers.forEach(layer => {
			if (
				!config.wmsExcludedLayers.includes(layer.name) &&
				!state.wsmLayers.some(existingLayer => existingLayer.name === layer.name)
			) {
				state.wsmLayers.push(layer);
			}
		});
	});

	// console.log('WSM layers',state.wsmLayers)

	return results;
}

// Function to fetch and parse the GetCapabilities document for a single WMS service
async function parseWMSCapabilities(wmsUrl) {
	const getCapabilitiesUrl = `${wmsUrl}${wmsUrl.includes('?') ? '&' : '?'}service=WMS&request=GetCapabilities`;
	try {
		const response = await fetch(getCapabilitiesUrl);
		const text = await response.text();
		const parser = new DOMParser();
		const xmlDoc = parser.parseFromString(text, "application/xml");

		const json = xmlToJson(xmlDoc);
		const service = json["WMS_Capabilities"]?.["Service"] || json["WMT_MS_Capabilities"]?.["Service"];
		const capability = json["WMS_Capabilities"]?.["Capability"] || json["WMT_MS_Capabilities"]?.["Capability"];
		const rootLayer = capability?.["Layer"];

		const providerTitle = service?.["Title"] || null;
		let organisationName = null;

		const contactInfo = service?.["ContactInformation"];
		if (contactInfo?.["ContactPersonPrimary"]) {
			organisationName = contactInfo["ContactPersonPrimary"]["ContactOrganization"];
		}

		// function extractLayers(layerNode, addedLayerNames, parentLayer) {
		function extractLayers(layerNode, parentLayer) {
			const layersData = [];
			const name = layerNode?.["Name"] || null;
			const abstract = layerNode?.["Abstract"] || null;
			const title = layerNode?.["Title"] || null;
			const childLayers = Array.isArray(layerNode?.["Layer"]) ? layerNode["Layer"] : null;

			var legendUrl = layerNode?.["Style"]?.["LegendURL"]?.["OnlineResource"]?.["xlink:href"] || null;
			if (!legendUrl && parentLayer?.legendUrl) {
				legendUrl = parentLayer.legendUrl;
			}


			var newLayer = {
				name,
				url: wmsUrl,
				title,
				abstract,
				legendUrl,
				visible: false,
				attribution: organisationName,
			}

			if (parentLayer) {
				newLayer.parentLayer = parentLayer;
			}
			if (childLayers) {
				newLayer.hasChildLayers = true;
			}

			layersData.push(newLayer);
	
			if (childLayers) {

				for (const child of childLayers) {
					if (child) {
						// extractLayers(child, newLayer);
						layersData.push(...extractLayers(child, newLayer));
					}
				}
			}

			return layersData;
		}

		// const addedLayerNames = new Set();
		const layersData = extractLayers(rootLayer);

		return {
			wmsUrl: wmsUrl,
			name: providerTitle,
			layers: layersData,
		};

	} catch (error) {
		console.error(`Error fetching or parsing GetCapabilities for ${wmsUrl}:`, error);
		return null;
	}
}


function cleanWmsUrl(url) {
	try {
		const parsedUrl = new URL(url);
		return `${parsedUrl.origin}${parsedUrl.pathname}`;
	} catch (e) {
		console.warn('Invalid WMS URL:', url);
		return url;
	}
}

function xmlToJson(xml) {
	const obj = {};

	// If element node
	if (xml.nodeType === 1) {
		// process attributes
		if (xml.attributes.length > 0) {
			for (let j = 0; j < xml.attributes.length; j++) {
				const attribute = xml.attributes.item(j);
				obj[attribute.nodeName] = attribute.nodeValue;
			}
		}
	}

	// If text node
	if (xml.nodeType === 3) {
		const trimmed = xml.nodeValue.trim();
		return trimmed === "" ? null : trimmed;
	}

	// Process children
	if (xml.hasChildNodes()) {
		let textOnly = true;
		let textContent = "";

		for (let i = 0; i < xml.childNodes.length; i++) {
			const item = xml.childNodes.item(i);
			const value = xmlToJson(item);

			if (item.nodeType === 3) {
				if (value) {
					textContent += value;
				}
			} else {
				textOnly = false;
				const nodeName = item.nodeName;
				if (value !== null) {
					if (obj[nodeName] === undefined) {
						obj[nodeName] = value;
					} else {
						if (!Array.isArray(obj[nodeName])) {
							obj[nodeName] = [obj[nodeName]];
						}
						obj[nodeName].push(value);
					}
				}
			}
		}

		if (textOnly) {
			return textContent;
		}
	}

	return obj;
}
