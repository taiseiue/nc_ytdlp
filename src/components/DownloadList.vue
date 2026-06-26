<template>
	<div class="download-list">
		<div class="list-header">
			<h3 class="section-title">
				{{ t('nc_ytdlp', 'Downloads') }}
			</h3>
			<div class="header-actions">
				<NcButton
					v-if="clearableCount > 0"
					type="tertiary"
					:aria-label="t('nc_ytdlp', 'Clear history')"
					:title="t('nc_ytdlp', 'Clear history')"
					:disabled="clearing"
					@click="clearHistory"
				>
					<template #icon>
						<NcLoadingIcon v-if="clearing" :size="18" />
						<DeleteSweepIcon v-else :size="18" />
					</template>
				</NcButton>
				<NcButton
					type="tertiary"
					:aria-label="t('nc_ytdlp', 'Refresh')"
					:disabled="loading"
					@click="refresh"
				>
					<template #icon>
						<RefreshIcon :size="18" />
					</template>
				</NcButton>
			</div>
		</div>

		<!-- Error state -->
		<NcNoteCard v-if="fetchError" type="error" class="list-error">
			{{ fetchError }}
		</NcNoteCard>

		<!-- Loading state (initial load only) -->
		<div v-else-if="loading && downloads.length === 0" class="state-placeholder">
			<NcLoadingIcon :size="32" />
			<p>{{ t('nc_ytdlp', 'Loading…') }}</p>
		</div>

		<!-- Empty state -->
		<div v-else-if="downloads.length === 0" class="state-placeholder">
			<DownloadOffIcon :size="40" class="placeholder-icon" />
			<p>{{ t('nc_ytdlp', 'No downloads yet. Paste a URL above to get started!') }}</p>
		</div>

		<!-- Download items -->
		<div v-else class="download-items">
			<TransitionGroup name="list">
				<div
					v-for="dl in downloads"
					:key="dl.id"
					class="download-item"
				>
					<!-- Format icon -->
					<div class="item-icon" :aria-label="dl.format.toUpperCase()">
						<VideoIcon v-if="dl.format === 'mp4'" :size="28" />
						<MusicIcon v-else :size="28" />
					</div>

					<!-- Info -->
					<div class="item-info">
						<div class="item-title" :title="dl.title || dl.url">
							{{ dl.title || dl.url }}
						</div>
						<div class="item-meta">
							<span class="badge" :class="`badge-${dl.format}`">
								{{ dl.format.toUpperCase() }}
							</span>
							<span v-if="dl.hasCookie" class="badge badge-cookie" :title="t('nc_ytdlp', 'Downloaded with cookies')">
								🍪
							</span>
							<FolderIcon :size="14" class="meta-icon" />
							<span class="meta-text" :title="dl.destination">{{ dl.destination }}</span>
							<span class="meta-dot">·</span>
							<span class="meta-text">{{ formatDate(dl.createdAt) }}</span>
						</div>
						<div v-if="dl.status === 'failed' && dl.errorMessage" class="item-error">
							<AlertIcon :size="14" class="meta-icon" />
							{{ dl.errorMessage }}
						</div>
					</div>

					<!-- Status + actions -->
					<div class="item-actions">
						<span :class="['status-chip', `status-${dl.status}`]">
							<NcLoadingIcon
								v-if="dl.status === 'running' || dl.status === 'pending'"
								:size="14"
								class="status-spinner"
							/>
							{{ statusLabel(dl.status) }}
						</span>
						<NcButton
							v-if="dl.status !== 'running' && dl.status !== 'pending'"
							type="tertiary"
							:aria-label="t('nc_ytdlp', 'Remove from list')"
							@click="removeDownload(dl.id)"
						>
							<template #icon>
								<DeleteIcon :size="18" />
							</template>
						</NcButton>
					</div>
				</div>
			</TransitionGroup>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DeleteSweepIcon from 'vue-material-design-icons/DeleteSweep.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import VideoIcon from 'vue-material-design-icons/FileVideo.vue'
import MusicIcon from 'vue-material-design-icons/FileMusic.vue'
import AlertIcon from 'vue-material-design-icons/AlertCircle.vue'
import DownloadOffIcon from 'vue-material-design-icons/DownloadOff.vue'

const POLL_INTERVAL_MS = 5000

export default {
	name: 'DownloadList',

	components: {
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		RefreshIcon,
		DeleteIcon,
		DeleteSweepIcon,
		FolderIcon,
		VideoIcon,
		MusicIcon,
		AlertIcon,
		DownloadOffIcon,
	},

	data() {
		return {
			downloads: [],
			loading: false,
			clearing: false,
			fetchError: null,
			pollTimer: null,
		}
	},

	computed: {
		// Completed and failed downloads can be cleared from the history;
		// pending and running ones are kept.
		clearableCount() {
			return this.downloads.filter(
				(d) => d.status === 'completed' || d.status === 'failed',
			).length
		},
	},

	mounted() {
		this.refresh()
		this.schedulePoll()
	},

	beforeDestroy() {
		this.clearPoll()
	},

	methods: {
		async refresh() {
			// Guard against concurrent fetches triggered by polling + manual refresh
			if (this.loading) {
				return
			}
			this.loading = true
			this.fetchError = null

			try {
				const { data } = await axios.get(generateUrl('/apps/nc_ytdlp/api/downloads'))
				this.downloads = data
			} catch (e) {
				this.fetchError = this.t('nc_ytdlp', 'Failed to load downloads. Please refresh.')
			} finally {
				this.loading = false
			}
		},

		async removeDownload(id) {
			try {
				await axios.delete(generateUrl(`/apps/nc_ytdlp/api/downloads/${id}`))
				this.downloads = this.downloads.filter((d) => d.id !== id)
			} catch {
				// Show nothing; the item stays in the list so the user can retry
			}
		},

		async clearHistory() {
			if (this.clearing) {
				return
			}
			const targets = this.downloads.filter(
				(d) => d.status === 'completed' || d.status === 'failed',
			)
			if (targets.length === 0) {
				return
			}

			this.clearing = true
			try {
				await axios.delete(generateUrl('/apps/nc_ytdlp/api/downloads/history'))
				this.downloads = this.downloads.filter(
					(d) => d.status !== 'completed' && d.status !== 'failed',
				)
			} catch (error) {
				console.error('Failed to clear history:', error)
				this.fetchError = this.t('nc_ytdlp', 'Failed to clear history. Please try again.')
				// Keep current list when server-side clear fails.
			} finally {
				this.clearing = false
			}
		},

		schedulePoll() {
			this.clearPoll()
			this.pollTimer = setInterval(() => {
				const hasActive = this.downloads.some(
					(d) => d.status === 'pending' || d.status === 'running',
				)
				if (hasActive) {
					this.refresh()
				}
			}, POLL_INTERVAL_MS)
		},

		clearPoll() {
			if (this.pollTimer !== null) {
				clearInterval(this.pollTimer)
				this.pollTimer = null
			}
		},

		statusLabel(status) {
			const map = {
				pending: this.t('nc_ytdlp', 'Pending'),
				running: this.t('nc_ytdlp', 'Downloading'),
				completed: this.t('nc_ytdlp', 'Completed'),
				failed: this.t('nc_ytdlp', 'Failed'),
			}
			return map[status] ?? status
		},

		formatDate(timestamp) {
			if (!timestamp) {
				return ''
			}
			return new Date(timestamp * 1000).toLocaleString()
		},
	},
}
</script>

<style scoped>
.download-list {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 24px;
}

.list-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 16px;
}

.section-title {
	font-size: 16px;
	font-weight: 600;
	margin: 0;
	color: var(--color-main-text);
}

.header-actions {
	display: flex;
	align-items: center;
	gap: 4px;
}

.list-error {
	margin-bottom: 12px;
}

.state-placeholder {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 40px 20px;
	gap: 12px;
	color: var(--color-text-maxcontrast);
}

.placeholder-icon {
	opacity: 0.4;
}

.download-items {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.download-item {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	padding: 14px 12px;
	border-radius: var(--border-radius-large);
	transition: background 0.15s;
}

.download-item:hover {
	background: var(--color-background-hover);
}

.item-icon {
	flex-shrink: 0;
	width: 40px;
	height: 40px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-background-dark);
	border-radius: 50%;
	color: var(--color-primary-element);
}

.item-info {
	flex: 1;
	min-width: 0;
}

.item-title {
	font-weight: 600;
	font-size: 14px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--color-main-text);
	margin-bottom: 4px;
}

.item-meta {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 4px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.meta-icon {
	opacity: 0.7;
}

.meta-text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 280px;
}

.meta-dot {
	opacity: 0.4;
}

.badge {
	padding: 1px 7px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.04em;
}

.badge-mp4 {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
}

.badge-mp3 {
	background: #e8f5e9;
	color: #388e3c;
}

.badge-cookie {
	background: none;
	padding: 0 2px;
	font-size: 13px;
}

.item-error {
	display: flex;
	align-items: flex-start;
	gap: 4px;
	margin-top: 6px;
	font-size: 12px;
	color: var(--color-error);
	word-break: break-word;
}

.item-actions {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	gap: 6px;
}

.status-chip {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 10px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
}

.status-pending {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.status-running {
	background: #fff3e0;
	color: #e65100;
}

.status-completed {
	background: var(--color-success-light, #e8f5e9);
	color: var(--color-success, #388e3c);
}

.status-failed {
	background: var(--color-error-light, #ffebee);
	color: var(--color-error);
}

/* Vue TransitionGroup animation */
.list-enter-active,
.list-leave-active {
	transition: all 0.3s ease;
}

.list-enter,
.list-leave-to {
	opacity: 0;
	transform: translateY(-8px);
}
</style>
