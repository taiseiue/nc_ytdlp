import Vue from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import App from './App.vue'

// Make translation helpers available in all component templates and methods
Vue.prototype.t = t
Vue.prototype.n = n

new Vue({
	render: (h) => h(App),
}).$mount('#nc-ytdlp-app')
