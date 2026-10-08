<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDocumentStore } from '@/stores/documents'
import { useAIStore } from '@/stores/ai'

const auth = useAuthStore()
const documentStore = useDocumentStore()
const aiStore = useAIStore()

// =========================
// UPLOAD STATE
// =========================

const showUploadModal = ref(false)
const selectedFile = ref(null)
const fileInput = ref(null)

// =========================
// PROCESSING POLLING
// =========================

let processingPoll = null

const hasPendingDocuments = computed(() => {
  return documentStore.documents.some((document) =>
    ['uploaded', 'processing'].includes(document.status),
  )
})

const stopProcessingPolling = () => {
  if (processingPoll) {
    clearInterval(processingPoll)
    processingPoll = null
  }
}

const checkProcessingStatus = async () => {
  await documentStore.fetchDocuments()

  if (!hasPendingDocuments.value) {
    stopProcessingPolling()
  }
}

const startProcessingPolling = () => {
  if (processingPoll) return

  processingPoll = setInterval(async () => {
    await checkProcessingStatus()
  }, 1000)
}

// =========================
// HELPERS
// =========================

const formatFileSize = (bytes) => {
  if (!bytes) return '0 KB'

  const kilobytes = bytes / 1024

  if (kilobytes < 1024) {
    return `${kilobytes.toFixed(1)} KB`
  }

  return `${(kilobytes / 1024).toFixed(1)} MB`
}

const formatDate = (date) => {
  if (!date) return '-'

  return new Intl.DateTimeFormat('en', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(date))
}

// =========================
// UPLOAD
// =========================

const openUploadModal = () => {
  selectedFile.value = null
  documentStore.error = null
  showUploadModal.value = true
}

const closeUploadModal = () => {
  if (documentStore.uploading) return

  showUploadModal.value = false
  selectedFile.value = null

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const openFilePicker = () => {
  fileInput.value?.click()
}

const handleFileSelection = (event) => {
  const file = event.target.files?.[0]

  if (!file) return

  selectedFile.value = file
  documentStore.error = null
}

const handleUpload = async () => {
  if (!selectedFile.value) return

  const result = await documentStore.uploadDocument(
    selectedFile.value,
  )

  if (result.success) {
    showUploadModal.value = false
    selectedFile.value = null

    if (fileInput.value) {
      fileInput.value.value = ''
    }

    /*
     * Laravel processes the document in the background.
     * Poll the API until processing has finished.
     */
    startProcessingPolling()
  }
}

// =========================
// LOAD DASHBOARD DATA
// =========================

onMounted(async () => {
  /*
   * Documents and conversations are independent,
   * so load them together when the dashboard opens.
   */
  await Promise.all([
    documentStore.fetchDocuments(),
    aiStore.fetchConversations(),
  ])

  /*
   * Resume polling if the user returns while a document
   * is still being processed.
   */
  if (hasPendingDocuments.value) {
    startProcessingPolling()
  }
})

// =========================
// CLEANUP
// =========================

onBeforeUnmount(() => {
  stopProcessingPolling()
})
</script>

<template>
  <div class="dashboard-page">

    <!-- =========================
         HEADER
    ========================== -->

    <header class="dashboard-header">

      <div>
        <p class="eyebrow">
          DASHBOARD
        </p>

        <h1>
          Welcome back,
          {{ auth.user?.name?.split(' ')[0] }}.
        </h1>

        <p class="header-description">
          Manage your documents and turn business information
          into useful insights.
        </p>
      </div>

      <button
        type="button"
        class="upload-button"
        @click="openUploadModal"
      >
        <span>+</span>
        Upload document
      </button>

    </header>

    <!-- =========================
         STATISTICS
    ========================== -->

    <section class="stats-grid">

      <!-- Documents -->

      <article class="stat-card">

        <div class="stat-label">
          Documents
        </div>

        <div class="stat-value">
          {{ documentStore.documentCount }}
        </div>

        <p>
          Total uploaded documents
        </p>

      </article>

      <!-- AI Ready -->

      <article class="stat-card">

        <div class="stat-label">
          AI ready
        </div>

        <div class="stat-value">
          {{ documentStore.processedCount }}
        </div>

        <p>
          Ready for AI analysis
        </p>

      </article>

      <!-- AI Conversations -->

      <article class="stat-card">

        <div class="stat-label">
          AI conversations
        </div>

        <div class="stat-value">
          {{ aiStore.conversations.length }}
        </div>

        <p>
          Saved AI conversations
        </p>

      </article>

    </section>

    <!-- =========================
         DOCUMENTS
    ========================== -->

    <section class="documents-section">

      <div class="section-heading">

        <div>
          <h2>
            Your documents
          </h2>

          <p>
            Recently uploaded files in your BusinessAI workspace.
          </p>
        </div>

        <button
          type="button"
          class="secondary-upload-button"
          @click="openUploadModal"
        >
          + Upload document
        </button>

      </div>

      <!-- Loading -->

      <div
        v-if="
          documentStore.loading &&
          documentStore.documents.length === 0
        "
        class="state-card"
      >
        Loading your documents...
      </div>

      <!-- Error -->

      <div
        v-else-if="
          documentStore.error &&
          !showUploadModal
        "
        class="state-card error-state"
      >
        {{ documentStore.error }}
      </div>

      <!-- Empty -->

      <div
        v-else-if="documentStore.documents.length === 0"
        class="empty-state"
      >

        <div class="empty-icon">

          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
          >
            <path d="M6 2h8l4 4v16H6z" />
            <path d="M14 2v5h5" />
            <path d="M9 13h6" />
            <path d="M9 17h4" />
          </svg>

        </div>

        <h3>
          No documents yet
        </h3>

        <p>
          Upload your first PDF to start building your
          BusinessAI workspace.
        </p>

        <button
          type="button"
          @click="openUploadModal"
        >
          Upload your first document
        </button>

      </div>

      <!-- =========================
           DOCUMENT TABLE
      ========================== -->

      <div
        v-else
        class="document-table"
      >

        <!-- Table header -->

        <div class="table-header">
          <span>Document</span>
          <span>Status</span>
          <span>Size</span>
          <span>Uploaded</span>
        </div>

        <!-- Documents -->

        <div
          v-for="document in documentStore.documents"
          :key="document.id"
          class="document-row"
        >

          <!-- Document -->

          <div class="document-name">

            <div class="pdf-icon">
              PDF
            </div>

            <div>

              <strong>
                {{ document.original_filename }}
              </strong>

              <span>
                {{ document.name }}
              </span>

            </div>

          </div>

          <!-- Status -->

          <div>
            <span
              class="status-badge"
              :class="`status-${document.status}`"
            >
              {{ document.status }}
            </span>
          </div>

          <!-- Size -->

          <span class="table-text">
            {{ formatFileSize(document.file_size) }}
          </span>

          <!-- Uploaded -->

          <span class="table-text">
            {{ formatDate(document.created_at) }}
          </span>

        </div>

      </div>

    </section>

    <!-- =========================
         UPLOAD MODAL
    ========================== -->

    <div
      v-if="showUploadModal"
      class="modal-backdrop"
      @click.self="closeUploadModal"
    >

      <div class="upload-modal">

        <!-- Header -->

        <div class="modal-header">

          <div>

            <h2>
              Upload a document
            </h2>

            <p>
              Add a PDF to your BusinessAI workspace.
            </p>

          </div>

          <button
            type="button"
            class="modal-close"
            aria-label="Close upload modal"
            @click="closeUploadModal"
          >
            ×
          </button>

        </div>

        <!-- File selector -->

        <div
          class="file-drop-area"
          @click="openFilePicker"
        >

          <input
            ref="fileInput"
            type="file"
            accept=".pdf,application/pdf"
            hidden
            @change="handleFileSelection"
          />

          <div class="upload-icon">
            ↑
          </div>

          <template v-if="!selectedFile">

            <strong>
              Choose a PDF document
            </strong>

            <p>
              Click here to select a file from your computer.
            </p>

          </template>

          <template v-else>

            <strong>
              {{ selectedFile.name }}
            </strong>

            <p>
              {{ formatFileSize(selectedFile.size) }}
            </p>

          </template>

        </div>

        <!-- Error -->

        <p
          v-if="documentStore.error"
          class="upload-error"
        >
          {{ documentStore.error }}
        </p>

        <!-- Actions -->

        <div class="modal-actions">

          <button
            type="button"
            class="cancel-button"
            :disabled="documentStore.uploading"
            @click="closeUploadModal"
          >
            Cancel
          </button>

          <button
            type="button"
            class="confirm-upload-button"
            :disabled="
              !selectedFile ||
              documentStore.uploading
            "
            @click="handleUpload"
          >
            {{
              documentStore.uploading
                ? 'Uploading...'
                : 'Upload document'
            }}
          </button>

        </div>

      </div>

    </div>

  </div>
</template>

<style scoped>

/* =========================
   PAGE
========================= */

.dashboard-page {
  width: 100%;
  max-width: 1500px;
  margin: 0 auto;
  padding: 52px clamp(32px, 5vw, 76px);
  color: #0f172a;
}

/* =========================
   HEADER
========================= */

.dashboard-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 30px;
}

.eyebrow {
  margin: 0 0 10px;
  color: #2563eb;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.16em;
}

.dashboard-header h1 {
  margin: 0;
  font-size: clamp(34px, 4vw, 48px);
  line-height: 1.05;
  letter-spacing: -0.045em;
}

.header-description {
  max-width: 580px;
  margin: 15px 0 0;
  color: #64748b;
  font-size: 15px;
  line-height: 1.7;
}

/* =========================
   BUTTONS
========================= */

.upload-button,
.secondary-upload-button,
.empty-state button {
  border: 0;
  border-radius: 9px;
  background: #0f172a;
  color: white;
  font: inherit;
  font-size: 13px;
  font-weight: 650;
  cursor: pointer;
  transition: background 0.2s;
}

.upload-button {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 13px 18px;
}

.upload-button span {
  font-size: 20px;
  line-height: 1;
}

.upload-button:hover,
.secondary-upload-button:hover,
.empty-state button:hover {
  background: #2563eb;
}

/* =========================
   STATS
========================= */

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
  margin-top: 48px;
}

.stat-card {
  padding: 23px;
  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: white;
}

.stat-label {
  color: #64748b;
  font-size: 12px;
  font-weight: 650;
}

.stat-value {
  margin-top: 18px;
  font-size: 32px;
  font-weight: 750;
  letter-spacing: -0.04em;
}

.stat-card p {
  margin: 5px 0 0;
  color: #94a3b8;
  font-size: 12px;
}

/* =========================
   DOCUMENTS
========================= */

.documents-section {
  margin-top: 48px;
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 18px;
}

.section-heading h2 {
  margin: 0;
  font-size: 21px;
  letter-spacing: -0.025em;
}

.section-heading p {
  margin: 7px 0 0;
  color: #64748b;
  font-size: 13px;
}

.secondary-upload-button {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #0f172a;
}

.secondary-upload-button:hover {
  border-color: #2563eb;
  color: white;
}

/* =========================
   STATES
========================= */

.state-card,
.empty-state {
  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: white;
}

.state-card {
  padding: 30px;
  color: #64748b;
  font-size: 14px;
}

.error-state {
  color: #dc2626;
}

.empty-state {
  padding: 70px 30px;
  text-align: center;
}

.empty-icon {
  width: 50px;
  height: 50px;
  display: grid;
  place-items: center;
  margin: 0 auto 18px;
  border-radius: 12px;
  background: #eff6ff;
  color: #2563eb;
}

.empty-icon svg {
  width: 24px;
}

.empty-state h3 {
  margin: 0;
  font-size: 17px;
}

.empty-state p {
  max-width: 380px;
  margin: 9px auto 20px;
  color: #64748b;
  font-size: 13px;
  line-height: 1.6;
}

.empty-state button {
  padding: 11px 16px;
}

/* =========================
   DOCUMENT TABLE
========================= */

.document-table {
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: white;
}

.table-header,
.document-row {
  display: grid;
  grid-template-columns:
    minmax(260px, 2fr)
    0.8fr
    0.7fr
    0.9fr;
  align-items: center;
  gap: 20px;
  padding: 15px 20px;
}

.table-header {
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #94a3b8;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.document-row {
  border-bottom: 1px solid #f1f5f9;
}

.document-row:last-child {
  border-bottom: 0;
}

.document-name {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.document-name > div:last-child {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.document-name strong {
  overflow: hidden;
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.document-name span {
  overflow: hidden;
  color: #94a3b8;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pdf-icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: #fef2f2;
  color: #dc2626;
  font-size: 9px;
  font-weight: 800;
}

/* =========================
   STATUS
========================= */

.status-badge {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 20px;
  background: #f1f5f9;
  color: #475569;
  font-size: 10px;
  font-weight: 700;
  text-transform: capitalize;
}

.status-uploaded {
  background: #eff6ff;
  color: #2563eb;
}

.status-processing {
  background: #fffbeb;
  color: #d97706;
}

.status-processed {
  background: #ecfdf5;
  color: #059669;
}

/*
 * A ready document has completed the full AI pipeline:
 * extraction -> chunking -> embeddings.
 */
.status-ready {
  background: #eef2ff;
  color: #4f46e5;
}

.status-failed {
  background: #fef2f2;
  color: #dc2626;
}

.table-text {
  color: #64748b;
  font-size: 12px;
}

/* =========================
   UPLOAD MODAL
========================= */

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(15 23 42 / 55%);
  backdrop-filter: blur(3px);
}

.upload-modal {
  width: 100%;
  max-width: 500px;
  padding: 28px;
  border-radius: 16px;
  background: white;
  box-shadow: 0 24px 70px rgb(15 23 42 / 20%);
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
}

.modal-header h2 {
  margin: 0;
  font-size: 21px;
  letter-spacing: -0.025em;
}

.modal-header p {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 13px;
}

.modal-close {
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 26px;
  line-height: 1;
  cursor: pointer;
}

.modal-close:hover {
  color: #0f172a;
}

.file-drop-area {
  margin-top: 25px;
  padding: 42px 25px;
  border: 1.5px dashed #cbd5e1;
  border-radius: 12px;
  background: #f8fafc;
  text-align: center;
  cursor: pointer;
  transition:
    border-color 0.2s,
    background 0.2s;
}

.file-drop-area:hover {
  border-color: #2563eb;
  background: #eff6ff;
}

.upload-icon {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  margin: 0 auto 14px;
  border-radius: 10px;
  background: #dbeafe;
  color: #2563eb;
  font-size: 22px;
  font-weight: 700;
}

.file-drop-area strong {
  display: block;
  font-size: 14px;
}

.file-drop-area p {
  margin: 6px 0 0;
  color: #94a3b8;
  font-size: 12px;
}

.upload-error {
  margin: 14px 0 0;
  color: #dc2626;
  font-size: 12px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 25px;
}

.cancel-button,
.confirm-upload-button {
  padding: 11px 16px;
  border-radius: 8px;
  font: inherit;
  font-size: 13px;
  font-weight: 650;
  cursor: pointer;
}

.cancel-button {
  border: 1px solid #e2e8f0;
  background: white;
  color: #475569;
}

.cancel-button:hover:not(:disabled) {
  background: #f8fafc;
}

.confirm-upload-button {
  border: 0;
  background: #0f172a;
  color: white;
}

.confirm-upload-button:hover:not(:disabled) {
  background: #2563eb;
}

.confirm-upload-button:disabled,
.cancel-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .dashboard-page {
    padding: 36px 20px;
  }

  .dashboard-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .section-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .table-header {
    display: none;
  }

  .document-row {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .upload-modal {
    padding: 22px;
  }
}

</style>