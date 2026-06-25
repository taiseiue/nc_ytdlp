<template>
	<NcContent app-name="nc_ytdlp">
		<NcAppContent>
			<div class="nc-ytdlp-wrapper">
				<div class="nc-ytdlp-header">
					<h2 class="nc-ytdlp-title">
						{{ t('nc_ytdlp', 'yt-dlp Downloader') }}
					</h2>
					<p class="nc-ytdlp-subtitle">
						{{ t('nc_ytdlp', 'Download videos and audio directly to your Nextcloud storage') }}
					</p>
				</div>
				<DownloadForm @submitted="onDownloadSubmitted" />
				<DownloadList ref="downloadList" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import DownloadForm from './components/DownloadForm.vue'
import DownloadList from './components/DownloadList.vue'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
		DownloadForm,
		DownloadList,
	},
	methods: {
		onDownloadSubmitted() {
			this.$refs.downloadList.refresh()
		},
	},
}
</script>

<style scoped>
.nc-ytdlp-wrapper {
	padding: 24px;
	max-width: 860px;
	margin: 0 auto;
}

.nc-ytdlp-header {
	margin-bottom: 28px;
}

.nc-ytdlp-title {
	font-size: 24px;
	font-weight: 700;
	margin: 0 0 6px;
	color: var(--color-main-text);
}

.nc-ytdlp-subtitle {
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
