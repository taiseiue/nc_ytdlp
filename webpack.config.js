const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = {
	...webpackConfig,
	entry: {
		main: path.join(__dirname, 'src', 'main.js'),
	},
}
