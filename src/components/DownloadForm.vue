<template>
	<div class="download-form">
		<h3 class="section-title">
			{{ t('nc_ytdlp', 'New Download') }}
		</h3>

		<!-- URL input -->
		<div class="form-field">
			<label class="form-label" for="ytdlp-url">
				{{ t('nc_ytdlp', 'Video URL') }}
			</label>
			<input
				id="ytdlp-url"
				v-model.trim="url"
				type="url"
				class="form-input"
				:placeholder="t('nc_ytdlp', 'https://www.youtube.com/watch?v=...')"
				@keydown.enter="submit"
			>
		</div>

		<!-- Format selection -->
		<div class="form-field">
			<fieldset class="format-fieldset">
				<legend class="form-label">{{ t('nc_ytdlp', 'Format') }}</legend>
				<div class="format-options">
					<label
						v-for="opt in formatOptions"
						:key="opt.value"
						class="format-option"
						:class="{ active: format === opt.value }"
					>
						<!-- Visually hidden but accessible to keyboard / screen readers -->
						<input
							v-model="format"
							type="radio"
							:value="opt.value"
							class="format-radio-input"
						>
						<span class="format-icon" aria-hidden="true">{{ opt.icon }}</span>
						<span class="format-text">
							<strong>{{ opt.label }}</strong>
							<small>{{ opt.description }}</small>
						</span>
					</label>
				</div>
			</fieldset>
		</div>

		<!-- Destination folder -->
		<div class="form-field">
			<label class="form-label">
				{{ t('nc_ytdlp', 'Destination folder') }}
			</label>
			<div class="destination-row">
				<span class="destination-path" :title="destination">{{ destination }}</span>
				<NcButton @click="pickFolder">
					<template #icon>
						<FolderIcon :size="18" />
					</template>
					{{ t('nc_ytdlp', 'Browse…') }}
				</NcButton>
			</div>
			<p class="field-hint">
				{{ t('nc_ytdlp', 'Navigate into the desired folder in the picker, then click "Choose" without selecting any file.') }}
			</p>
		</div>

		<!-- Advanced options -->
		<div class="form-field">
			<button class="advanced-toggle" type="button" @click="showAdvanced = !showAdvanced">
				<ChevronDownIcon v-if="showAdvanced" :size="16" />
				<ChevronRightIcon v-else :size="16" />
				{{ t('nc_ytdlp', 'Advanced options') }}
			</button>
			<div v-if="showAdvanced" class="advanced-content">
				<!-- Output template -->
				<label class="form-label" for="ytdlp-output-template">
					{{ t('nc_ytdlp', 'Output filename template') }}
				</label>

				<!-- Template bank: saved templates -->
				<div v-if="savedTemplates.length" class="template-bank">
					<span class="template-bank-label">{{ t('nc_ytdlp', 'Saved templates') }}</span>
					<div class="template-bank-chips">
						<div
							v-for="tpl in savedTemplates"
							:key="tpl.id"
							class="saved-template-chip"
							:class="{ active: outputTemplate === tpl.template }"
						>
							<button
								type="button"
								class="saved-template-apply"
								:title="tpl.template"
								@click="applyTemplate(tpl)"
							>
								{{ tpl.name }}
							</button>
							<button
								type="button"
								class="saved-template-delete"
								:title="t('nc_ytdlp', 'Delete template')"
								@click="deleteTemplate(tpl)"
							>
								<CloseIcon :size="14" />
							</button>
						</div>
					</div>
				</div>

				<input
					id="ytdlp-output-template"
					v-model.trim="outputTemplate"
					type="text"
					class="form-input"
					:placeholder="t('nc_ytdlp', '%(title)s.%(ext)s')"
				>
				<p class="field-hint">
					{{ t('nc_ytdlp', 'Leave blank to use the default: %(title)s.%(ext)s') }}
				</p>

				<!-- Save current template -->
				<div class="template-save">
					<template v-if="!savingTemplate">
						<button
							class="template-save-toggle"
							type="button"
							:disabled="!outputTemplate"
							@click="startSaveTemplate"
						>
							<ContentSaveIcon :size="14" />
							{{ t('nc_ytdlp', 'Save current template') }}
						</button>
					</template>
					<div v-else class="template-save-form">
						<input
							ref="templateNameInput"
							v-model.trim="newTemplateName"
							type="text"
							class="form-input template-name-input"
							:placeholder="t('nc_ytdlp', 'Template name')"
							@keydown.enter.prevent="confirmSaveTemplate"
							@keydown.esc="cancelSaveTemplate"
						>
						<NcButton type="primary" :disabled="!newTemplateName || savingInProgress" @click="confirmSaveTemplate">
							{{ t('nc_ytdlp', 'Save') }}
						</NcButton>
						<NcButton @click="cancelSaveTemplate">
							{{ t('nc_ytdlp', 'Cancel') }}
						</NcButton>
					</div>
					<p v-if="templateError" class="field-hint template-error">
						{{ templateError }}
					</p>
				</div>

				<!-- Template field helper -->
				<button class="template-fields-toggle" type="button" @click="showTemplateFields = !showTemplateFields">
					<ChevronDownIcon v-if="showTemplateFields" :size="14" />
					<ChevronRightIcon v-else :size="14" />
					{{ t('nc_ytdlp', 'Available fields') }}
				</button>
				<div v-if="showTemplateFields" class="template-fields">
					<p class="field-hint">
						{{ t('nc_ytdlp', 'Click a field to insert it into the template.') }}
					</p>
					<div class="template-field-chips">
						<button
							v-for="field in templateFields"
							:key="field.token"
							type="button"
							class="template-field-chip"
							:title="field.label"
							@click="insertField(field.token)"
						>
							<code>{{ field.token }}</code>
							<span>{{ field.label }}</span>
						</button>
					</div>
					<p class="field-hint">
						<a
							href="https://github.com/yt-dlp/yt-dlp#output-template"
							target="_blank"
							rel="noopener noreferrer"
							class="template-fields-link"
						>
							{{ t('nc_ytdlp', 'See the full list of available fields (yt-dlp documentation)') }}
						</a>
					</p>
				</div>

				<!-- Cookie string -->
				<div class="cookie-label-row">
					<label class="form-label" for="ytdlp-cookie">
						{{ t('nc_ytdlp', 'Cookie string') }}
					</label>
					<span v-if="hasSavedCookie" class="cookie-saved-badge">
						{{ t('nc_ytdlp', 'Last used value restored') }}
					</span>
				</div>
				<textarea
					id="ytdlp-cookie"
					v-model.trim="cookie"
					class="form-textarea"
					rows="3"
					:placeholder="t('nc_ytdlp', 'name1=value1; name2=value2 (from browser DevTools → Application → Cookies)')"
				/>
				<p class="field-hint">
					{{ t('nc_ytdlp', 'Paste the Cookie header value to access members-only or age-restricted content.') }}
				</p>
			</div>
		</div>

		<!-- Submit -->
		<div class="form-actions">
			<NcButton
				type="primary"
				:disabled="!url || loading"
				@click="submit"
			>
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="18" />
				</template>
				<template v-else #icon>
					<DownloadIcon :size="18" />
				</template>
				{{ loading ? t('nc_ytdlp', 'Queuing…') : t('nc_ytdlp', 'Start Download') }}
			</NcButton>
		</div>

		<!-- Feedback messages -->
		<NcNoteCard v-if="error" type="error" class="form-feedback">
			{{ error }}
		</NcNoteCard>
		<NcNoteCard v-else-if="success" type="success" class="form-feedback">
			{{ t('nc_ytdlp', 'Download queued successfully! It will appear in the list below.') }}
		</NcNoteCard>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'

export default {
	name: 'DownloadForm',

	components: {
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		FolderIcon,
		DownloadIcon,
		ChevronDownIcon,
		ChevronRightIcon,
		CloseIcon,
		ContentSaveIcon,
	},

	emits: ['submitted'],

	data() {
		return {
			url: '',
			format: 'mp4',
			destination: '/',
			outputTemplate: '',
			cookie: '',
			showAdvanced: false,
			showTemplateFields: false,
			savedTemplates: [],
			savingTemplate: false,
			savingInProgress: false,
			newTemplateName: '',
			templateError: null,
			loading: false,
			error: null,
			success: false,
			successTimer: null,
		}
	},

	mounted() {
		const saved = localStorage.getItem('nc_ytdlp_last_cookie')
		if (saved) {
			this.cookie = saved
			this.showAdvanced = true
		}
		this.fetchTemplates()
	},

	computed: {
		hasSavedCookie() {
			return !!localStorage.getItem('nc_ytdlp_last_cookie')
		},

		templateFields() {
			return [
				{ token: '%(title)s', label: this.t('nc_ytdlp', 'Video title') },
				{ token: '%(ext)s', label: this.t('nc_ytdlp', 'File extension') },
				{ token: '%(id)s', label: this.t('nc_ytdlp', 'Video ID') },
				{ token: '%(uploader)s', label: this.t('nc_ytdlp', 'Uploader') },
				{ token: '%(channel)s', label: this.t('nc_ytdlp', 'Channel name') },
				{ token: '%(upload_date)s', label: this.t('nc_ytdlp', 'Upload date (YYYYMMDD)') },
				{ token: '%(duration)s', label: this.t('nc_ytdlp', 'Duration (seconds)') },
				{ token: '%(resolution)s', label: this.t('nc_ytdlp', 'Resolution') },
			]
		},

		formatOptions() {
			return [
				{
					value: 'mp4',
					icon: '🎬',
					label: 'MP4',
					description: this.t('nc_ytdlp', 'Video with audio'),
				},
				{
					value: 'mp3',
					icon: '🎵',
					label: 'MP3',
					description: this.t('nc_ytdlp', 'Audio only'),
				},
			]
		},
	},

	beforeDestroy() {
		// Prevent setting state on an unmounted component
		if (this.successTimer !== null) {
			clearTimeout(this.successTimer)
		}
	},

	methods: {
		insertField(token) {
			this.outputTemplate = (this.outputTemplate || '') + token
		},

		async fetchTemplates() {
			try {
				const { data } = await axios.get(generateUrl('/apps/nc_ytdlp/api/templates'))
				this.savedTemplates = Array.isArray(data) ? data : []
			} catch {
				// Non-fatal: the bank just stays empty if it can't be loaded.
			}
		},

		applyTemplate(tpl) {
			this.outputTemplate = tpl.template
		},

		startSaveTemplate() {
			this.templateError = null
			this.newTemplateName = ''
			this.savingTemplate = true
			this.$nextTick(() => {
				this.$refs.templateNameInput?.focus()
			})
		},

		cancelSaveTemplate() {
			this.savingTemplate = false
			this.newTemplateName = ''
			this.templateError = null
		},

		async confirmSaveTemplate() {
			if (!this.newTemplateName || !this.outputTemplate || this.savingInProgress) {
				return
			}
			this.savingInProgress = true
			this.templateError = null
			try {
				const { data } = await axios.post(generateUrl('/apps/nc_ytdlp/api/templates'), {
					name: this.newTemplateName,
					template: this.outputTemplate,
				})
				this.savedTemplates.push(data)
				this.savedTemplates.sort((a, b) => a.name.localeCompare(b.name))
				this.savingTemplate = false
				this.newTemplateName = ''
			} catch (e) {
				this.templateError = e.response?.data?.error
					?? this.t('nc_ytdlp', 'Failed to save template.')
			} finally {
				this.savingInProgress = false
			}
		},

		async deleteTemplate(tpl) {
			try {
				await axios.delete(generateUrl('/apps/nc_ytdlp/api/templates/{id}', { id: tpl.id }))
				this.savedTemplates = this.savedTemplates.filter(t => t.id !== tpl.id)
			} catch (e) {
				this.templateError = e.response?.data?.error
					?? this.t('nc_ytdlp', 'Failed to delete template.')
			}
		},

		async pickFolder() {
			try {
				const picker = getFilePickerBuilder(this.t('nc_ytdlp', 'Select destination folder'))
					.setMimeTypeFilter(['httpd/unix-directory'])
					.allowDirectories(true)
					.setMultiSelect(false)
					.setType(FilePickerType.Choose)
					.startAt(this.destination)
					.build()

				// pick() returns a string path for single-select
				const path = await picker.pick()
				if (typeof path === 'string' && path) {
					this.destination = path
				}
			} catch {
				// FilePickerClosed or user cancelled — keep the current destination
			}
		},

		async submit() {
			if (!this.url || this.loading) {
				return
			}

			this.loading = true
			this.error = null
			this.success = false

			if (this.successTimer !== null) {
				clearTimeout(this.successTimer)
				this.successTimer = null
			}

			try {
				await axios.post(generateUrl('/apps/nc_ytdlp/api/downloads'), {
					url: this.url,
					format: this.format,
					destination: this.destination,
					cookie: this.cookie,
					outputTemplate: this.outputTemplate,
				})

				this.url = ''
				this.success = true
				this.$emit('submitted')

				if (this.cookie) {
					localStorage.setItem('nc_ytdlp_last_cookie', this.cookie)
				} else {
					localStorage.removeItem('nc_ytdlp_last_cookie')
				}

				this.successTimer = setTimeout(() => {
					this.success = false
					this.successTimer = null
				}, 5000)
			} catch (e) {
				this.error = e.response?.data?.error
					?? this.t('nc_ytdlp', 'Failed to queue download. Please try again.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.download-form {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 24px;
	margin-bottom: 24px;
}

.section-title {
	font-size: 16px;
	font-weight: 600;
	margin: 0 0 20px;
	color: var(--color-main-text);
}

.form-field {
	margin-bottom: 20px;
}

.form-label {
	display: block;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-bottom: 8px;
}

.form-input {
	width: 100%;
	height: 44px;
	padding: 0 12px;
	font-size: 14px;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	box-sizing: border-box;
	transition: border-color 0.2s;
}

.form-input:focus {
	outline: none;
	border-color: var(--color-primary-element);
}

/* Format picker */
.format-fieldset {
	border: none;
	margin: 0;
	padding: 0;
}

.format-options {
	display: flex;
	gap: 12px;
}

.format-option {
	flex: 1;
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 12px 16px;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: border-color 0.2s, background 0.2s;
}

/* Visually hidden but accessible to keyboard and screen readers */
.format-radio-input {
	position: absolute;
	opacity: 0;
	width: 1px;
	height: 1px;
	pointer-events: none;
}

.format-option:has(.format-radio-input:focus-visible) {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.format-option.active {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.format-icon {
	font-size: 22px;
}

.format-text {
	display: flex;
	flex-direction: column;
}

.format-text strong {
	font-size: 15px;
	color: var(--color-main-text);
}

.format-text small {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

/* Destination */
.destination-row {
	display: flex;
	align-items: center;
	gap: 10px;
}

.destination-path {
	flex: 1;
	padding: 10px 12px;
	font-family: monospace;
	font-size: 13px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	color: var(--color-main-text);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Advanced */
.advanced-toggle {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	padding: 0;
}

.advanced-toggle:hover {
	color: var(--color-main-text);
}

.advanced-content {
	margin-top: 12px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.advanced-content .form-label {
	margin-bottom: 4px;
}

.advanced-content .field-hint {
	margin-top: 4px;
}

.form-textarea {
	width: 100%;
	padding: 10px 12px;
	font-size: 13px;
	font-family: monospace;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	box-sizing: border-box;
	resize: vertical;
	transition: border-color 0.2s;
}

.form-textarea:focus {
	outline: none;
	border-color: var(--color-primary-element);
}

.field-hint {
	margin: 6px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

/* Template field helper */
.template-fields-toggle {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	padding: 0;
	margin-top: 6px;
}

.template-fields-toggle:hover {
	color: var(--color-main-text);
}

.template-fields {
	margin-top: 8px;
}

.template-field-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 8px 0;
}

.template-field-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 10px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	cursor: pointer;
	font-size: 12px;
	color: var(--color-main-text);
	transition: border-color 0.2s, background 0.2s;
}

.template-field-chip:hover {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.template-field-chip code {
	font-family: monospace;
	font-size: 12px;
	color: var(--color-primary-element);
}

.template-field-chip span {
	color: var(--color-text-maxcontrast);
}

.template-fields-link {
	color: var(--color-primary-element);
	text-decoration: underline;
}

/* Template bank */
.template-bank {
	margin-bottom: 8px;
}

.template-bank-label {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 6px;
}

.template-bank-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.saved-template-chip {
	display: inline-flex;
	align-items: stretch;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	background: var(--color-background-dark);
	transition: border-color 0.2s, background 0.2s;
}

.saved-template-chip.active {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.saved-template-apply {
	background: none;
	border: none;
	cursor: pointer;
	padding: 4px 10px;
	font-size: 13px;
	color: var(--color-main-text);
}

.saved-template-apply:hover {
	color: var(--color-primary-element);
}

.saved-template-delete {
	display: inline-flex;
	align-items: center;
	background: none;
	border: none;
	border-left: 1px solid var(--color-border-dark);
	cursor: pointer;
	padding: 0 6px;
	color: var(--color-text-maxcontrast);
}

.saved-template-delete:hover {
	color: var(--color-error, #d32f2f);
}

/* Save current template */
.template-save {
	margin-top: 8px;
}

.template-save-toggle {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	padding: 0;
}

.template-save-toggle:hover:not(:disabled) {
	color: var(--color-main-text);
}

.template-save-toggle:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.template-save-form {
	display: flex;
	align-items: center;
	gap: 8px;
}

.template-name-input {
	flex: 1;
	height: 38px;
}

.template-error {
	color: var(--color-error, #d32f2f);
}

.form-actions {
	margin-top: 8px;
}

.form-feedback {
	margin-top: 16px;
}

/* Cookie label row */
.cookie-label-row {
	display: flex;
	align-items: baseline;
	gap: 8px;
	margin-bottom: 8px;
}

.cookie-label-row .form-label {
	margin-bottom: 0;
}

.cookie-saved-badge {
	font-size: 11px;
	font-weight: 600;
	color: var(--color-success-text, #2d7d46);
	background: var(--color-success-background, #e6f4ea);
	border-radius: 4px;
	padding: 1px 6px;
}
</style>
