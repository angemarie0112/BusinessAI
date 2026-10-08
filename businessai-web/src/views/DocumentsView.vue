<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentStore } from '@/stores/documents'

const router = useRouter()
const documentStore = useDocumentStore()

// =========================
// STATE
// =========================

const fileInput = ref(null)
const searchQuery = ref('')
const statusFilter = ref('all')

const documentToDelete = ref(null)

// =========================
// DOCUMENT FILTERING
// =========================

const filteredDocuments = computed(() => {
  const search = searchQuery.value.trim().toLowerCase()

  return documentStore.documents.filter((document) => {
    const filename =
      document.original_filename?.toLowerCase() ?? ''

    const name =
      document.name?.toLowerCase() ?? ''

    const matchesSearch =
      !search ||
      filename.includes(search) ||
      name.includes(search)

    const matchesStatus =
      statusFilter.value === 'all' ||
      document.status === statusFilter.value

    return matchesSearch && matchesStatus
  })
})

// =========================
// FILE HELPERS
// =========================

const formatFileSize = (bytes) => {
  const size = Number(bytes)

  if (!size || Number.isNaN(size)) {
    return '—'
  }

  if (size < 1024) {
    return `${size} B`
  }

  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(1)} KB`
  }

  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

const formatDate = (date) => {
  if (!date) {
    return '—'
  }

  const parsedDate = new Date(date)

  if (Number.isNaN(parsedDate.getTime())) {
    return '—'
  }

  return new Intl.DateTimeFormat('en', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(parsedDate)
}

const documentName = (document) => {
  return (
    document?.original_filename ||
    document?.name ||
    'Untitled document'
  )
}

// =========================
// STATUS
// =========================

const statusLabel = (status) => {
  const labels = {
    uploaded: 'Uploaded',
    processing: 'Processing',
    processed: 'Processed',
    ready: 'Ready',
    failed: 'Failed',
  }

  return labels[status] ?? status ?? 'Unknown'
}

const statusClass = (status) => {
  return `status-${status ?? 'unknown'}`
}

/*
 * Human-friendly description of what BusinessAI
 * is currently doing with the document.
 */
const preparationLabel = (status) => {
  const labels = {
    uploaded: 'Waiting to process...',
    processing: 'Processing document...',
    processed: 'Preparing AI...',
    failed: 'Needs attention',
  }

  return labels[status] ?? 'Preparing...'
}

/*
 * These statuses mean BusinessAI is still actively
 * preparing the document for Ask AI.
 */
const isPreparing = (status) => {
  return [
    'uploaded',
    'processing',
    'processed',
  ].includes(status)
}

// =========================
// UPLOAD
// =========================

const openFilePicker = () => {
  if (documentStore.uploading) {
    return
  }

  fileInput.value?.click()
}

const handleFileChange = async (event) => {
  const file = event.target.files?.[0]

  if (!file) {
    return
  }

  await documentStore.uploadDocument(file)

  /*
   * Reset the input so the same file can be
   * selected again later if necessary.
   */
  event.target.value = ''

  /*
   * Refresh from Laravel after upload so this page
   * stays synchronized with the backend.
   */
  await documentStore.fetchDocuments()
}

// =========================
// ASK AI
// =========================

const askDocument = (document) => {
  /*
   * Only AI-ready documents can be opened
   * inside the Ask AI workspace.
   */
  if (document.status !== 'ready') {
    return
  }

  router.push({
    name: 'ask-ai',
    query: {
      document: String(document.id),
    },
  })
}

// =========================
// DELETE DOCUMENT
// =========================

const openDeleteModal = (document) => {
  /*
   * Store the document the user wants to delete.
   */
  documentToDelete.value = document

  documentStore.clearError()
}

const closeDeleteModal = () => {
  /*
   * Do not close the modal while Laravel is
   * processing the delete request.
   */
  if (
    documentToDelete.value &&
    documentStore.isDeleting(documentToDelete.value.id)
  ) {
    return
  }

  documentToDelete.value = null
}

const confirmDelete = async () => {
  if (!documentToDelete.value) {
    return
  }

  const documentId = documentToDelete.value.id

  const result = await documentStore.deleteDocument(
    documentId,
  )

  /*
   * Only close the modal if Laravel successfully
   * deleted the document.
   */
  if (result.success) {
    documentToDelete.value = null
  }
}

// =========================
// LOAD DOCUMENTS
// =========================

onMounted(async () => {
  await documentStore.fetchDocuments()

  /*
   * Keep checking Laravel while documents are
   * being prepared so their status updates
   * automatically without refreshing the browser.
   */
  documentStore.startStatusPolling()
})
</script>

<template>
  <div class="documents-page">

    <!-- =========================
         HEADER
    ========================== -->

    <header class="page-header">
      <div>
        <p class="eyebrow">
          DOCUMENTS
        </p>

        <h1>
          Your knowledge base
        </h1>

        <p class="header-description">
          Upload and manage the documents BusinessAI uses
          to answer your questions.
        </p>
      </div>

      <div>
        <input
          ref="fileInput"
          type="file"
          accept=".pdf,application/pdf"
          class="file-input"
          @change="handleFileChange"
        >

        <button
          type="button"
          class="upload-button"
          :disabled="documentStore.uploading"
          @click="openFilePicker"
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>

          <span>
            {{
              documentStore.uploading
                ? 'Uploading...'
                : 'Upload document'
            }}
          </span>
        </button>
      </div>
    </header>

    <!-- =========================
         SUMMARY
    ========================== -->

    <section class="summary-grid">

      <article class="summary-card">
        <div class="summary-icon">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <path d="M6 2h8l4 4v16H6z" />
            <path d="M14 2v5h5" />
          </svg>
        </div>

        <div>
          <span class="summary-label">
            Total documents
          </span>

          <strong>
            {{ documentStore.documentCount }}
          </strong>
        </div>
      </article>

      <article class="summary-card">
        <div class="summary-icon ready-icon">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <path d="m5 12 4 4L19 6" />
          </svg>
        </div>

        <div>
          <span class="summary-label">
            AI ready
          </span>

          <strong>
            {{ documentStore.readyDocuments.length }}
          </strong>
        </div>
      </article>

    </section>

    <!-- =========================
         ERROR
    ========================== -->

    <div
      v-if="documentStore.error"
      class="error-message"
    >
      {{ documentStore.error }}
    </div>

    <!-- =========================
         DOCUMENT PANEL
    ========================== -->

    <section class="documents-panel">

      <!-- TOOLBAR -->

      <div class="toolbar">

        <div class="search-box">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <circle
              cx="11"
              cy="11"
              r="7"
            />

            <path d="m20 20-3.5-3.5" />
          </svg>

          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search documents..."
          >
        </div>

        <select
          v-model="statusFilter"
          class="status-filter"
          aria-label="Filter documents by status"
        >
          <option value="all">
            All statuses
          </option>

          <option value="ready">
            Ready
          </option>

          <option value="processing">
            Processing
          </option>

          <option value="processed">
            Processed
          </option>

          <option value="uploaded">
            Uploaded
          </option>

          <option value="failed">
            Failed
          </option>
        </select>

      </div>

      <!-- LOADING -->

      <div
        v-if="
          documentStore.loading &&
          documentStore.documents.length === 0
        "
        class="state-container"
      >
        <div class="loading-spinner"></div>

        <p>
          Loading your documents...
        </p>
      </div>

      <!-- NO DOCUMENTS -->

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

        <h2>
          No documents yet
        </h2>

        <p>
          Upload your first PDF and BusinessAI will
          prepare it for AI-powered questions.
        </p>

        <button
          type="button"
          class="empty-upload-button"
          @click="openFilePicker"
        >
          Upload your first document
        </button>
      </div>

      <!-- NO SEARCH RESULTS -->

      <div
        v-else-if="filteredDocuments.length === 0"
        class="no-results"
      >
        <h2>
          No matching documents
        </h2>

        <p>
          Try changing your search or status filter.
        </p>
      </div>

      <!-- DOCUMENT TABLE -->

      <div
        v-else
        class="table-wrapper"
      >
        <table>

          <thead>
            <tr>
              <th>Document</th>
              <th>Status</th>
              <th>Size</th>
              <th>Uploaded</th>

              <th class="action-heading">
                Actions
              </th>
            </tr>
          </thead>

          <tbody>

            <tr
              v-for="document in filteredDocuments"
              :key="document.id"
            >

              <!-- DOCUMENT -->

              <td>
                <div class="document-cell">

                  <div class="pdf-icon">
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

                  <div class="document-info">
                    <strong>
                      {{ documentName(document) }}
                    </strong>

                    <span>
                      PDF document
                    </span>
                  </div>

                </div>
              </td>

              <!-- STATUS -->

              <td>
                <span
                  class="status-badge"
                  :class="[
                    statusClass(document.status),
                    {
                      'status-active':
                        isPreparing(document.status),
                    },
                  ]"
                >
                  <span class="status-dot"></span>

                  {{ statusLabel(document.status) }}
                </span>
              </td>

              <!-- SIZE -->

              <td class="muted-cell">
                {{ formatFileSize(document.file_size) }}
              </td>

              <!-- DATE -->

              <td class="muted-cell">
                {{ formatDate(document.created_at) }}
              </td>

              <!-- ACTIONS -->

              <td class="action-cell">

                <div class="action-buttons">

                  <button
                    v-if="document.status === 'ready'"
                    type="button"
                    class="ask-button"
                    @click="askDocument(document)"
                  >
                    Ask AI

                    <span>
                      →
                    </span>
                  </button>

                  <div
                    v-else
                    class="preparation-state"
                    :class="{
                      'preparation-failed':
                        document.status === 'failed',
                    }"
                  >
                    <span
                      v-if="isPreparing(document.status)"
                      class="preparation-spinner"
                    ></span>

                    <span>
                      {{ preparationLabel(document.status) }}
                    </span>
                  </div>

                  <button
                    type="button"
                    class="delete-button"
                    :disabled="
                      documentStore.isDeleting(document.id)
                    "
                    :aria-label="
                      `Delete ${documentName(document)}`
                    "
                    @click="openDeleteModal(document)"
                  >
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                    >
                      <path d="M3 6h18" />
                      <path d="M8 6V4h8v2" />
                      <path d="M19 6l-1 14H6L5 6" />
                      <path d="M10 10v6" />
                      <path d="M14 10v6" />
                    </svg>
                  </button>

                </div>

              </td>

            </tr>

          </tbody>
        </table>
      </div>

    </section>

    <!-- =========================
         DELETE CONFIRMATION
    ========================== -->

    <div
      v-if="documentToDelete"
      class="modal-backdrop"
      @click.self="closeDeleteModal"
    >

      <div
        class="delete-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-document-title"
      >

        <div class="delete-modal-icon">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <path d="M3 6h18" />
            <path d="M8 6V4h8v2" />
            <path d="M19 6l-1 14H6L5 6" />
            <path d="M10 10v6" />
            <path d="M14 10v6" />
          </svg>
        </div>

        <h2 id="delete-document-title">
          Delete document?
        </h2>

        <p>
          You are about to permanently delete
          <strong>
            {{ documentName(documentToDelete) }}
          </strong>.
          Its AI chunks and embeddings will also no longer
          be available.
        </p>

        <div
          v-if="documentStore.error"
          class="modal-error"
        >
          {{ documentStore.error }}
        </div>

        <div class="modal-actions">

          <button
            type="button"
            class="cancel-button"
            :disabled="
              documentStore.isDeleting(
                documentToDelete.id,
              )
            "
            @click="closeDeleteModal"
          >
            Cancel
          </button>

          <button
            type="button"
            class="confirm-delete-button"
            :disabled="
              documentStore.isDeleting(
                documentToDelete.id,
              )
            "
            @click="confirmDelete"
          >
            {{
              documentStore.isDeleting(
                documentToDelete.id,
              )
                ? 'Deleting...'
                : 'Delete document'
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

.documents-page {
  width: 100%;
  max-width: 1500px;
  min-width: 0;
  margin: 0 auto;
  padding: 52px clamp(32px, 5vw, 76px);
  color: #0f172a;
}

/* =========================
   HEADER
========================= */

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
}

.eyebrow {
  margin: 0 0 10px;
  color: #2563eb;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.16em;
}

.page-header h1 {
  margin: 0;
  font-size: clamp(34px, 4vw, 48px);
  line-height: 1.05;
  letter-spacing: -0.045em;
}

.header-description {
  max-width: 620px;
  margin: 15px 0 0;
  color: #64748b;
  font-size: 15px;
  line-height: 1.7;
}

.file-input {
  display: none;
}

.upload-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-width: 165px;
  padding: 12px 17px;
  border: 0;
  border-radius: 9px;
  background: #0f172a;
  color: white;
  font: inherit;
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
  transition:
    background 0.2s,
    opacity 0.2s;
}

.upload-button svg {
  width: 16px;
  height: 16px;
}

.upload-button:hover:not(:disabled) {
  background: #2563eb;
}

.upload-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

/* =========================
   SUMMARY
========================= */

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 220px));
  gap: 14px;
  margin-top: 34px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 17px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: white;
}

.summary-icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: #eff6ff;
  color: #2563eb;
}

.summary-icon svg {
  width: 18px;
  height: 18px;
}

.ready-icon {
  background: #ecfdf5;
  color: #059669;
}

.summary-label {
  display: block;
  margin-bottom: 2px;
  color: #64748b;
  font-size: 11px;
}

.summary-card strong {
  display: block;
  color: #0f172a;
  font-size: 20px;
  line-height: 1.2;
}

/* =========================
   ERROR
========================= */

.error-message {
  margin-top: 20px;
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 9px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 12px;
}

/* =========================
   DOCUMENT PANEL
========================= */

.documents-panel {
  margin-top: 24px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: white;
}

/* =========================
   TOOLBAR
========================= */

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid #e2e8f0;
}

.search-box {
  width: min(100%, 420px);
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: white;
}

.search-box:focus-within {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgb(37 99 235 / 7%);
}

.search-box svg {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  color: #94a3b8;
}

.search-box input {
  width: 100%;
  padding: 10px 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: #0f172a;
  font: inherit;
  font-size: 12px;
}

.search-box input::placeholder {
  color: #94a3b8;
}

.status-filter {
  min-width: 150px;
  padding: 10px 34px 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: white;
  color: #475569;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}

.status-filter:focus {
  border-color: #2563eb;
}

/* =========================
   TABLE
========================= */

.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  padding: 12px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
}

td {
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 12px;
  vertical-align: middle;
}

tbody tr:last-child td {
  border-bottom: 0;
}

tbody tr {
  transition: background 0.15s;
}

tbody tr:hover {
  background: #fafcff;
}

.document-cell {
  min-width: 250px;
  display: flex;
  align-items: center;
  gap: 11px;
}

.pdf-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: #f8fafc;
  color: #64748b;
}

.pdf-icon svg {
  width: 18px;
  height: 18px;
}

.document-info {
  min-width: 0;
}

.document-info strong {
  max-width: 320px;
  display: block;
  overflow: hidden;
  color: #1e293b;
  font-size: 12px;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.document-info span {
  display: block;
  margin-top: 3px;
  color: #94a3b8;
  font-size: 10px;
}

.muted-cell {
  color: #64748b;
  white-space: nowrap;
}

/* =========================
   STATUS
========================= */

.status-badge {
  width: fit-content;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 650;
  white-space: nowrap;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-ready {
  background: #ecfdf5;
  color: #047857;
}

.status-processing,
.status-uploaded {
  background: #eff6ff;
  color: #2563eb;
}

.status-processed {
  background: #f8fafc;
  color: #64748b;
}

.status-failed {
  background: #fef2f2;
  color: #dc2626;
}

.status-unknown {
  background: #f8fafc;
  color: #64748b;
}

/*
 * Pulse the status dot while BusinessAI is
 * actively preparing the document.
 */
.status-active .status-dot {
  animation: statusPulse 1.5s ease-in-out infinite;
}

@keyframes statusPulse {
  0%,
  100% {
    opacity: 0.4;
    transform: scale(0.8);
  }

  50% {
    opacity: 1;
    transform: scale(1.15);
  }
}

/* =========================
   ACTIONS
========================= */

.action-heading,
.action-cell {
  text-align: right;
}

.action-buttons {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.ask-button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 11px;
  border: 1px solid #dbeafe;
  border-radius: 7px;
  background: #eff6ff;
  color: #2563eb;
  font: inherit;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
  transition:
    background 0.2s,
    color 0.2s;
}

.ask-button:hover {
  background: #2563eb;
  color: white;
}

/* =========================
   PREPARATION STATE
========================= */

.preparation-state {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 7px;
  color: #64748b;
  font-size: 10px;
  white-space: nowrap;
}

.preparation-spinner {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
  border: 1.5px solid #cbd5e1;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: preparationSpin 0.75s linear infinite;
}

@keyframes preparationSpin {
  to {
    transform: rotate(360deg);
  }
}

.preparation-failed {
  color: #dc2626;
}

/* =========================
   DELETE BUTTON
========================= */

.delete-button {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  background: white;
  color: #94a3b8;
  cursor: pointer;
  transition:
    border-color 0.2s,
    background 0.2s,
    color 0.2s;
}

.delete-button svg {
  width: 15px;
  height: 15px;
}

.delete-button:hover:not(:disabled) {
  border-color: #fecaca;
  background: #fef2f2;
  color: #dc2626;
}

.delete-button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

/* =========================
   STATES
========================= */

.state-container,
.empty-state,
.no-results {
  padding: 70px 30px;
  text-align: center;
}

.state-container {
  color: #64748b;
  font-size: 12px;
}

.loading-spinner {
  width: 25px;
  height: 25px;
  margin: 0 auto 13px;
  border: 2px solid #e2e8f0;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty-icon {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  margin: 0 auto 17px;
  border-radius: 12px;
  background: #eff6ff;
  color: #2563eb;
}

.empty-icon svg {
  width: 23px;
  height: 23px;
}

.empty-state h2,
.no-results h2 {
  margin: 0;
  color: #0f172a;
  font-size: 18px;
}

.empty-state p,
.no-results p {
  max-width: 430px;
  margin: 8px auto 18px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.7;
}

.empty-upload-button {
  padding: 10px 14px;
  border: 0;
  border-radius: 8px;
  background: #0f172a;
  color: white;
  font: inherit;
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
}

.empty-upload-button:hover {
  background: #2563eb;
}

/* =========================
   DELETE MODAL
========================= */

.modal-backdrop {
  position: fixed;
  z-index: 1000;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(15 23 42 / 38%);
  backdrop-filter: blur(2px);
}

.delete-modal {
  width: min(100%, 430px);
  padding: 26px;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
  box-shadow:
    0 20px 50px rgb(15 23 42 / 18%);
}

.delete-modal-icon {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  margin-bottom: 18px;
  border-radius: 11px;
  background: #fef2f2;
  color: #dc2626;
}

.delete-modal-icon svg {
  width: 20px;
  height: 20px;
}

.delete-modal h2 {
  margin: 0;
  color: #0f172a;
  font-size: 20px;
  letter-spacing: -0.025em;
}

.delete-modal > p {
  margin: 10px 0 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.7;
}

.delete-modal > p strong {
  color: #334155;
  font-weight: 700;
}

.modal-error {
  margin-top: 16px;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 11px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  margin-top: 24px;
}

.cancel-button,
.confirm-delete-button {
  padding: 10px 14px;
  border-radius: 8px;
  font: inherit;
  font-size: 11px;
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
  color: #0f172a;
}

.confirm-delete-button {
  border: 1px solid #dc2626;
  background: #dc2626;
  color: white;
}

.confirm-delete-button:hover:not(:disabled) {
  border-color: #b91c1c;
  background: #b91c1c;
}

.cancel-button:disabled,
.confirm-delete-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .search-box {
    width: 100%;
    max-width: none;
  }

  .status-filter {
    width: 100%;
  }
}

@media (max-width: 760px) {
  .documents-page {
    padding: 36px 20px;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .upload-button {
    width: 100%;
  }

  th,
  td {
    padding-right: 14px;
    padding-left: 14px;
  }

  .delete-modal {
    padding: 22px;
  }

  .modal-actions {
    flex-direction: column-reverse;
  }

  .cancel-button,
  .confirm-delete-button {
    width: 100%;
  }
}
</style>