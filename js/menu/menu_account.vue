<template>
	<div class="menuwindow account">

		<div class="menuwindow-header">
			<h3>Account</h3>
		</div>

		<div class="menuwindow-content">

			
			
		</div>

	</div>

</template>

<script>

import { state } from '../state.js' 
import { dataModel } from '@/datamodel.js'

export default {
	name: 'AccountMenu',
	components: {
	},
	setup() {
		return {state};
	},
	data() {
		return {
		}
	},
	computed: {
	},
	props: {
	},
	methods: {
	
	},
	watch: {
	
	},
	mounted() {
	}
}
</script>

<style lang="stylus" scoped>

.menuwindow.settings
	width 400px

// .settings
// 	user-select none

.notice
	opacity .5

.split
	margin-top -2px
	display flex
	justify-content space-between
	font-size 10px
	// margin-bottom 12px
	.right
		text-align right

</style>
