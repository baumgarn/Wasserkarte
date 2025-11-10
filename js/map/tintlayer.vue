<template>
	<ol-vector-layer>
		<ol-source-vector>
			<ol-feature>
				<ol-geom-polygon :coordinates="worldPolygon" />
			</ol-feature>
		</ol-source-vector>

		<ol-style>
			<ol-style-fill :color="color" />
		</ol-style>
	</ol-vector-layer>
</template>

<script>
import { fromLonLat } from 'ol/proj';

export default {
	name: 'FlatTintLayer',
	props: {
		color: {
			type: String,
			default: 'rgba(0,120,255,0.15)'
		}
	},
	computed: {
		worldPolygon() {
			const ring = [
				[-180, -85], [180, -85], [180, 85], [-180, 85], [-180, -85]
			];
			return [ring.map(c => fromLonLat(c))];
		}
	}
};
</script>