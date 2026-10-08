import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useDocumentStore = defineStore('documents', () => {
  const documents = ref([])
  const loading = ref(false)
  const uploading = ref(false)
  const deletingDocumentId = ref(null)
  const error = ref(null)

  /*
   * Holds the JavaScript interval used to check Laravel
   * for updated document statuses.
   *
   * This does not need to be reactive because the UI
   * never displays the interval itself.
   */
  let pollingInterval = null

  // =========================
  // COUNTS
  // =========================

  const documentCount = computed(() => {
    return documents.value.length
  })

  const processedCount = computed(() => {
    return documents.value.filter((document) =>
      ['processed', 'ready'].includes(document.status),
    ).length
  })

  const readyDocuments = computed(() => {
    return documents.value.filter(
      (document) => document.status === 'ready',
    )
  })

  /*
   * These statuses mean Laravel / BusinessAI still has
   * work to do for at least one document.
   */
  const hasPendingDocuments = computed(() => {
    return documents.value.some((document) =>
      [
        'uploaded',
        'processing',
        'processed',
      ].includes(document.status),
    )
  })

  // =========================
  // ERROR
  // =========================

  const clearError = () => {
    error.value = null
  }

  // =========================
  // FETCH DOCUMENTS
  // =========================

  const fetchDocuments = async ({
    silent = false,
  } = {}) => {
    /*
     * During normal page loading we show the loading
     * state.
     *
     * During background polling we use silent=true so
     * the page does not flash "Loading..." every few
     * seconds.
     */
    if (!silent) {
      loading.value = true
    }

    error.value = null

    try {
      const response = await api.get('/documents')

      documents.value = response.data

      return {
        success: true,
        documents: response.data,
      }
    } catch (err) {
      /*
       * A background polling failure should not destroy
       * the current document list.
       */
      if (!silent) {
        error.value = 'Unable to load documents.'
      }

      console.error(
        'Unable to load documents:',
        err,
      )

      return {
        success: false,
      }
    } finally {
      if (!silent) {
        loading.value = false
      }
    }
  }

  // =========================
  // STATUS POLLING
  // =========================

  const stopStatusPolling = () => {
    if (!pollingInterval) {
      return
    }

    clearInterval(pollingInterval)
    pollingInterval = null
  }

  const startStatusPolling = () => {
    /*
     * Never create two polling intervals.
     */
    if (pollingInterval) {
      return
    }

    /*
     * There is nothing to monitor if all documents
     * already finished processing.
     */
    if (!hasPendingDocuments.value) {
      return
    }

    pollingInterval = setInterval(
      async () => {
        await fetchDocuments({
          silent: true,
        })

        /*
         * Stop automatically once there are no documents
         * left in an intermediate state.
         *
         * At this point every document should normally be
         * either:
         *
         * ready
         * failed
         */
        if (!hasPendingDocuments.value) {
          stopStatusPolling()
        }
      },
      3000,
    )
  }

  // =========================
  // UPLOAD DOCUMENT
  // =========================

  const uploadDocument = async (file) => {
    uploading.value = true
    error.value = null

    const formData = new FormData()

    formData.append('file', file)

    try {
      const response = await api.post(
        '/documents',
        formData,
      )

      documents.value.unshift(
        response.data.document,
      )

      /*
       * The new document starts as "uploaded".
       *
       * Start monitoring it immediately so the UI can
       * automatically move through:
       *
       * uploaded
       * → processing
       * → ready
       */
      startStatusPolling()

      return {
        success: true,
        document: response.data.document,
      }
    } catch (err) {
      if (err.response?.status === 422) {
        error.value =
          err.response.data.errors?.file?.[0] ??
          'The selected document is invalid.'
      } else {
        error.value = 'Unable to upload document.'
      }

      console.error(
        'Unable to upload document:',
        err,
      )

      return {
        success: false,
      }
    } finally {
      uploading.value = false
    }
  }

  // =========================
  // DELETE DOCUMENT
  // =========================

  const deleteDocument = async (documentId) => {
    if (
      String(deletingDocumentId.value) ===
      String(documentId)
    ) {
      return {
        success: false,
      }
    }

    deletingDocumentId.value = documentId
    error.value = null

    try {
      await api.delete(
        `/documents/${documentId}`,
      )

      documents.value = documents.value.filter(
        (document) =>
          String(document.id) !==
          String(documentId),
      )

      /*
       * If the deleted document was the last pending
       * document, polling is no longer necessary.
       */
      if (!hasPendingDocuments.value) {
        stopStatusPolling()
      }

      return {
        success: true,
      }
    } catch (err) {
      if (err.response?.status === 404) {
        error.value =
          'This document could not be found.'
      } else if (err.response?.status === 403) {
        error.value =
          'You are not authorized to delete this document.'
      } else {
        error.value =
          'Unable to delete the document. Please try again.'
      }

      console.error(
        'Unable to delete document:',
        err,
      )

      return {
        success: false,
      }
    } finally {
      deletingDocumentId.value = null
    }
  }

  // =========================
  // DELETE STATE
  // =========================

  const isDeleting = (documentId) => {
    return (
      String(deletingDocumentId.value) ===
      String(documentId)
    )
  }

  // =========================
  // STORE API
  // =========================

  return {
    documents,
    loading,
    uploading,
    deletingDocumentId,
    error,

    documentCount,
    processedCount,
    readyDocuments,
    hasPendingDocuments,

    clearError,
    fetchDocuments,
    uploadDocument,
    deleteDocument,
    isDeleting,

    startStatusPolling,
    stopStatusPolling,
  }
})