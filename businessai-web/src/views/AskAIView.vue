<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDocumentStore } from '@/stores/documents'
import { useAIStore } from '@/stores/ai'

const route = useRoute()
const router = useRouter()

const auth = useAuthStore()
const documentStore = useDocumentStore()
const aiStore = useAIStore()

// =========================
// STATE
// =========================

const selectedDocumentId = ref('')
const currentConversationId = ref(null)
const question = ref('')
const messages = ref([])
const conversationArea = ref(null)

const openMenuConversationId = ref(null)
const conversationToDelete = ref(null)

// =========================
// DOCUMENTS
// =========================

const readyDocuments = computed(() => {
  return documentStore.documents.filter(
    (document) => document.status === 'ready',
  )
})

const selectedDocument = computed(() => {
  if (!selectedDocumentId.value) {
    return null
  }

  return readyDocuments.value.find(
    (document) =>
      String(document.id) === String(selectedDocumentId.value),
  )
})

// =========================
// CONVERSATIONS
// =========================

const conversations = computed(() => {
  return aiStore.conversations
})

const conversationDocumentName = (conversation) => {
  return (
    conversation.document?.original_filename ||
    'Document'
  )
}

const conversationTitle = (conversation) => {
  return conversation.title || 'New conversation'
}

// =========================
// USER
// =========================

const userInitial = computed(() => {
  return auth.user?.name?.charAt(0).toUpperCase() || 'U'
})

// =========================
// SUGGESTED QUESTIONS
// =========================

const suggestedQuestions = [
  'Summarize the main points of this document.',
  'What are the most important recommendations?',
  'What key information should I pay attention to?',
]

// =========================
// HELPERS
// =========================

const scrollToBottom = async () => {
  await nextTick()

  if (conversationArea.value) {
    conversationArea.value.scrollTop =
      conversationArea.value.scrollHeight
  }
}

const selectSuggestion = (suggestion) => {
  question.value = suggestion
}

/**
 * Clear only the local conversation state.
 *
 * Nothing is deleted from the database.
 */
const resetConversation = () => {
  messages.value = []
  currentConversationId.value = null
  question.value = ''

  aiStore.clearCurrentConversation()
  aiStore.clearError()
}

/**
 * Convert messages returned by Laravel into the
 * structure used by this page.
 */
const setMessagesFromConversation = (conversation) => {
  const savedMessages = conversation?.messages ?? []

  messages.value = savedMessages.map((message) => ({
    id: message.id,
    role: message.role,
    content: message.content,
    model: message.model,
  }))
}

// =========================
// DOCUMENT SELECTION
// =========================

const selectDocumentFromRoute = () => {
  if (readyDocuments.value.length === 0) {
    selectedDocumentId.value = ''
    return
  }

  const requestedDocumentId = route.query.document

  const requestedDocument = readyDocuments.value.find(
    (document) =>
      String(document.id) === String(requestedDocumentId),
  )

  if (requestedDocument) {
    selectedDocumentId.value =
      String(requestedDocument.id)

    return
  }

  selectedDocumentId.value =
    String(readyDocuments.value[0].id)
}

/**
 * Load one saved conversation.
 */
const loadConversation = async (conversationId) => {
  if (!conversationId) {
    return false
  }

  const result = await aiStore.fetchConversation(
    conversationId,
  )

  if (!result.success || !result.data) {
    return false
  }

  const conversation = result.data

  if (
    String(conversation.document_id) !==
    String(selectedDocumentId.value)
  ) {
    aiStore.clearCurrentConversation()
    return false
  }

  currentConversationId.value = conversation.id

  setMessagesFromConversation(conversation)

  await scrollToBottom()

  return true
}

/**
 * Restore a conversation from:
 *
 * ?document=8&conversation=1
 */
const restoreConversationFromRoute = async () => {
  const conversationId = route.query.conversation

  if (!conversationId) {
    return
  }

  const loaded = await loadConversation(
    conversationId,
  )

  if (!loaded) {
    currentConversationId.value = null
    messages.value = []

    await router.replace({
      name: 'ask-ai',
      query: {
        document: selectedDocumentId.value,
      },
    })
  }
}

/**
 * User manually changes the document selector.
 */
const handleDocumentChange = async () => {
  resetConversation()
  openMenuConversationId.value = null

  await router.replace({
    name: 'ask-ai',
    query: {
      document: selectedDocumentId.value,
    },
  })
}

// =========================
// CONVERSATION HISTORY
// =========================

/**
 * Open one of the saved conversations from the
 * history panel.
 */
const openConversation = async (conversation) => {
  if (
    aiStore.asking ||
    aiStore.loadingConversation ||
    aiStore.creatingConversation ||
    aiStore.deletingConversation
  ) {
    return
  }

  aiStore.clearError()
  openMenuConversationId.value = null

  selectedDocumentId.value =
    String(conversation.document_id)

  currentConversationId.value =
    conversation.id

  question.value = ''

  await router.replace({
    name: 'ask-ai',
    query: {
      document: conversation.document_id,
      conversation: conversation.id,
    },
  })

  const loaded = await loadConversation(
    conversation.id,
  )

  if (!loaded) {
    resetConversation()

    await router.replace({
      name: 'ask-ai',
      query: {
        document: selectedDocumentId.value,
      },
    })
  }
}

/**
 * Start a new conversation while keeping the
 * currently selected document.
 */
const startNewChat = async () => {
  if (
    aiStore.asking ||
    aiStore.loadingConversation ||
    aiStore.creatingConversation ||
    aiStore.deletingConversation
  ) {
    return
  }

  openMenuConversationId.value = null
  resetConversation()

  await router.replace({
    name: 'ask-ai',
    query: {
      document: selectedDocumentId.value,
    },
  })
}

// =========================
// DELETE CONVERSATION
// =========================

const toggleConversationMenu = (
  conversationId,
  event,
) => {
  event.stopPropagation()

  if (
    aiStore.asking ||
    aiStore.loadingConversation ||
    aiStore.creatingConversation ||
    aiStore.deletingConversation
  ) {
    return
  }

  if (
    String(openMenuConversationId.value) ===
    String(conversationId)
  ) {
    openMenuConversationId.value = null
    return
  }

  openMenuConversationId.value = conversationId
}

const requestDeleteConversation = (
  conversation,
  event,
) => {
  event.stopPropagation()

  openMenuConversationId.value = null
  conversationToDelete.value = conversation
}

const cancelDeleteConversation = () => {
  if (aiStore.deletingConversation) {
    return
  }

  conversationToDelete.value = null
}

/**
 * Permanently delete the selected conversation.
 *
 * If it is the active conversation, clear the chat
 * and remove the conversation ID from the URL while
 * keeping the same document selected.
 */
const confirmDeleteConversation = async () => {
  if (
    !conversationToDelete.value ||
    aiStore.deletingConversation
  ) {
    return
  }

  const conversation =
    conversationToDelete.value

  const deletingActiveConversation =
    String(currentConversationId.value) ===
    String(conversation.id)

  const result = await aiStore.deleteConversation(
    conversation.id,
  )

  if (!result.success) {
    return
  }

  conversationToDelete.value = null
  openMenuConversationId.value = null

  if (deletingActiveConversation) {
    resetConversation()

    await router.replace({
      name: 'ask-ai',
      query: {
        document: selectedDocumentId.value,
      },
    })
  }
}

// =========================
// CONVERSATION CREATION
// =========================

/**
 * Create a conversation only when the first question
 * is actually sent.
 */
const ensureConversation = async () => {
  if (currentConversationId.value) {
    return currentConversationId.value
  }

  if (!selectedDocument.value) {
    return null
  }

  const result = await aiStore.createConversation(
    selectedDocument.value.id,
  )

  if (!result.success || !result.data) {
    return null
  }

  currentConversationId.value =
    result.data.id

  await router.replace({
    name: 'ask-ai',
    query: {
      document: selectedDocumentId.value,
      conversation: currentConversationId.value,
    },
  })

  return currentConversationId.value
}

// =========================
// ASK AI
// =========================

const askQuestion = async () => {
  const trimmedQuestion = question.value.trim()

  if (
    !trimmedQuestion ||
    !selectedDocument.value ||
    aiStore.asking ||
    aiStore.creatingConversation ||
    aiStore.loadingConversation ||
    aiStore.deletingConversation
  ) {
    return
  }

  aiStore.clearError()

  const conversationId =
    await ensureConversation()

  if (!conversationId) {
    return
  }

  const temporaryUserMessageId =
    `user-${Date.now()}`

  messages.value.push({
    id: temporaryUserMessageId,
    role: 'user',
    content: trimmedQuestion,
  })

  question.value = ''

  await scrollToBottom()

  const result = await aiStore.askDocument(
    selectedDocument.value.id,
    conversationId,
    trimmedQuestion,
  )

  if (!result.success) {
    messages.value = messages.value.filter(
      (message) =>
        message.id !== temporaryUserMessageId,
    )

    question.value = trimmedQuestion

    await scrollToBottom()

    return
  }

  const conversationResult =
    await aiStore.fetchConversation(
      conversationId,
    )

  if (
    conversationResult.success &&
    conversationResult.data
  ) {
    setMessagesFromConversation(
      conversationResult.data,
    )
  } else {
    messages.value.push({
      id: `assistant-${Date.now()}`,
      role: 'assistant',
      content: result.data.answer,
      model: result.data.model,
    })
  }

  await aiStore.fetchConversations()

  await scrollToBottom()
}

const handleQuestionKeydown = (event) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    askQuestion()
  }
}

// =========================
// ROUTE CHANGES
// =========================

watch(
  () => route.query.document,
  async (newDocumentId, oldDocumentId) => {
    if (
      newDocumentId === oldDocumentId ||
      readyDocuments.value.length === 0
    ) {
      return
    }

    if (
      String(newDocumentId) ===
      String(selectedDocumentId.value)
    ) {
      return
    }

    resetConversation()

    selectDocumentFromRoute()

    await restoreConversationFromRoute()
  },
)

// =========================
// INITIAL LOAD
// =========================

onMounted(async () => {
  await Promise.all([
    documentStore.fetchDocuments(),
    aiStore.fetchConversations(),
  ])

  selectDocumentFromRoute()

  if (
    selectedDocumentId.value &&
    !route.query.document
  ) {
    await router.replace({
      name: 'ask-ai',
      query: {
        document: selectedDocumentId.value,
      },
    })
  }

  if (route.query.conversation) {
    await restoreConversationFromRoute()
  }
})
</script>

<template>
  <div class="ask-page">

    <!-- =========================
         HEADER
    ========================== -->

    <header class="page-header">

      <div>
        <p class="eyebrow">
          AI WORKSPACE
        </p>

        <h1>
          Ask BusinessAI
        </h1>

        <p class="header-description">
          Ask questions and get answers grounded in your
          AI-ready business documents.
        </p>
      </div>

      <div
        v-if="readyDocuments.length > 0"
        class="document-selector"
      >

        <label for="document">
          Document
        </label>

        <select
          id="document"
          v-model="selectedDocumentId"
          :disabled="
            aiStore.asking ||
            aiStore.creatingConversation ||
            aiStore.loadingConversation ||
            aiStore.deletingConversation
          "
          @change="handleDocumentChange"
        >
          <option
            v-for="document in readyDocuments"
            :key="document.id"
            :value="String(document.id)"
          >
            {{ document.original_filename }}
          </option>
        </select>

      </div>

    </header>

    <!-- =========================
         LOADING DOCUMENTS
    ========================== -->

    <section
      v-if="
        documentStore.loading &&
        documentStore.documents.length === 0
      "
      class="state-card"
    >
      Loading your documents...
    </section>

    <!-- =========================
         NO READY DOCUMENTS
    ========================== -->

    <section
      v-else-if="readyDocuments.length === 0"
      class="empty-documents"
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
        No documents ready for AI
      </h2>

      <p>
        Upload a PDF and wait for BusinessAI to finish
        preparing it before asking questions.
      </p>

      <RouterLink
        to="/documents"
        class="dashboard-link"
      >
        Go to documents
      </RouterLink>

    </section>

    <!-- =========================
         AI WORKSPACE
    ========================== -->

    <section
      v-else
      class="workspace"
    >

      <div class="workspace-body">

        <!-- =========================
             CONVERSATION HISTORY
        ========================== -->

        <aside class="conversation-sidebar">

          <div class="conversation-sidebar-header">

            <div>
              <span class="conversation-label">
                Conversations
              </span>

              <span class="conversation-count">
                {{ conversations.length }}
              </span>
            </div>

            <button
              type="button"
              class="new-chat-button"
              :disabled="
                aiStore.asking ||
                aiStore.loadingConversation ||
                aiStore.creatingConversation ||
                aiStore.deletingConversation
              "
              @click="startNewChat"
            >
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path d="M12 5v14" />
                <path d="M5 12h14" />
              </svg>

              <span>
                New chat
              </span>
            </button>

          </div>

          <div class="conversation-list">

            <div
              v-if="conversations.length === 0"
              class="no-conversations"
            >
              <span>
                No conversations yet.
              </span>

              <small>
                Ask your first question to create one.
              </small>
            </div>

            <div
              v-for="conversation in conversations"
              v-else
              :key="conversation.id"
              class="conversation-item-wrapper"
            >

              <button
                type="button"
                class="conversation-item"
                :class="{
                  active:
                    String(currentConversationId) ===
                    String(conversation.id),
                }"
                :disabled="
                  aiStore.asking ||
                  aiStore.loadingConversation ||
                  aiStore.creatingConversation ||
                  aiStore.deletingConversation
                "
                @click="openConversation(conversation)"
              >

                <span class="conversation-item-icon">
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                  >
                    <path
                      d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"
                    />
                  </svg>
                </span>

                <span class="conversation-item-content">

                  <span class="conversation-title">
                    {{ conversationTitle(conversation) }}
                  </span>

                  <span class="conversation-document">
                    {{ conversationDocumentName(conversation) }}
                  </span>

                </span>

              </button>

              <button
                type="button"
                class="conversation-menu-button"
                aria-label="Conversation options"
                :disabled="
                  aiStore.asking ||
                  aiStore.loadingConversation ||
                  aiStore.creatingConversation ||
                  aiStore.deletingConversation
                "
                @click="
                  toggleConversationMenu(
                    conversation.id,
                    $event,
                  )
                "
              >
                <svg
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <circle cx="5" cy="12" r="1.6" />
                  <circle cx="12" cy="12" r="1.6" />
                  <circle cx="19" cy="12" r="1.6" />
                </svg>
              </button>

              <div
                v-if="
                  String(openMenuConversationId) ===
                  String(conversation.id)
                "
                class="conversation-menu"
              >
                <button
                  type="button"
                  class="delete-menu-button"
                  @click="
                    requestDeleteConversation(
                      conversation,
                      $event,
                    )
                  "
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
                    <path d="M10 11v5" />
                    <path d="M14 11v5" />
                  </svg>

                  Delete
                </button>
              </div>

            </div>

          </div>

        </aside>

        <!-- =========================
             CHAT AREA
        ========================== -->

        <div class="chat-area">

          <div
            ref="conversationArea"
            class="conversation"
          >

            <div
              v-if="aiStore.loadingConversation"
              class="conversation-loading"
            >
              Loading conversation...
            </div>

            <div
              v-else-if="messages.length === 0"
              class="welcome-state"
            >

              <div class="ai-mark">
                ✦
              </div>

              <h2>
                Ask anything about
                {{ selectedDocument?.original_filename }}
              </h2>

              <p>
                BusinessAI will search the document for relevant
                information and use it to answer your question.
              </p>

              <div class="suggestions">

                <button
                  v-for="suggestion in suggestedQuestions"
                  :key="suggestion"
                  type="button"
                  @click="selectSuggestion(suggestion)"
                >

                  <span>
                    {{ suggestion }}
                  </span>

                  <span class="suggestion-arrow">
                    →
                  </span>

                </button>

              </div>

            </div>

            <div
              v-else
              class="messages"
            >

              <article
                v-for="message in messages"
                :key="message.id"
                class="message"
                :class="`message-${message.role}`"
              >

                <template v-if="message.role === 'user'">

                  <div class="message-user-row">

                    <div class="message-avatar user-message-avatar">
                      {{ userInitial }}
                    </div>

                    <div>

                      <span class="message-author">
                        You
                      </span>

                      <div class="user-bubble">
                        {{ message.content }}
                      </div>

                    </div>

                  </div>

                </template>

                <template v-else>

                  <div class="assistant-heading">

                    <div class="message-avatar ai-message-avatar">
                      ✦
                    </div>

                    <div>

                      <span class="message-author">
                        BusinessAI
                      </span>

                      <span
                        v-if="message.model"
                        class="model-name"
                      >
                        {{ message.model }}
                      </span>

                    </div>

                  </div>

                  <div class="assistant-answer">
                    {{ message.content }}
                  </div>

                </template>

              </article>

              <div
                v-if="aiStore.asking"
                class="thinking"
              >

                <div class="message-avatar ai-message-avatar">
                  ✦
                </div>

                <div>

                  <span class="message-author">
                    BusinessAI
                  </span>

                  <div class="thinking-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                  </div>

                </div>

              </div>

              <div
                v-if="aiStore.error"
                class="ai-error"
              >
                {{ aiStore.error }}
              </div>

            </div>

          </div>

          <!-- =========================
               QUESTION INPUT
          ========================== -->

          <div class="composer-wrapper">

            <div class="composer">

              <textarea
                v-model="question"
                rows="1"
                :disabled="
                  aiStore.asking ||
                  aiStore.creatingConversation ||
                  aiStore.loadingConversation ||
                  aiStore.deletingConversation
                "
                :placeholder="
                  `Ask a question about ${
                    selectedDocument?.original_filename ??
                    'this document'
                  }...`
                "
                @keydown="handleQuestionKeydown"
              ></textarea>

              <button
                type="button"
                class="send-button"
                :disabled="
                  !question.trim() ||
                  !selectedDocument ||
                  aiStore.asking ||
                  aiStore.creatingConversation ||
                  aiStore.loadingConversation ||
                  aiStore.deletingConversation
                "
                aria-label="Ask BusinessAI"
                @click="askQuestion"
              >

                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M5 12h14" />
                  <path d="m13 6 6 6-6 6" />
                </svg>

              </button>

            </div>

            <p class="composer-note">
              BusinessAI answers using information retrieved from
              the selected document.
            </p>

          </div>

        </div>

      </div>

    </section>

    <!-- =========================
         DELETE CONFIRMATION
    ========================== -->

    <div
      v-if="conversationToDelete"
      class="modal-backdrop"
      @click.self="cancelDeleteConversation"
    >

      <div
        class="delete-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-conversation-title"
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
            <path d="M10 11v5" />
            <path d="M14 11v5" />
          </svg>
        </div>

        <h2 id="delete-conversation-title">
          Delete conversation?
        </h2>

        <p>
          This conversation and all of its messages will
          be permanently deleted. This action cannot be
          undone.
        </p>

        <div class="delete-conversation-name">
          {{ conversationTitle(conversationToDelete) }}
        </div>

        <div
          v-if="aiStore.error"
          class="delete-modal-error"
        >
          {{ aiStore.error }}
        </div>

        <div class="delete-modal-actions">

          <button
            type="button"
            class="cancel-delete-button"
            :disabled="aiStore.deletingConversation"
            @click="cancelDeleteConversation"
          >
            Cancel
          </button>

          <button
            type="button"
            class="confirm-delete-button"
            :disabled="aiStore.deletingConversation"
            @click="confirmDeleteConversation"
          >
            {{
              aiStore.deletingConversation
                ? 'Deleting...'
                : 'Delete'
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

.ask-page {
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
  gap: 30px;
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

/* =========================
   DOCUMENT SELECTOR
========================= */

.document-selector {
  width: 300px;
  flex-shrink: 0;
}

.document-selector label {
  display: block;
  margin-bottom: 7px;
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.document-selector select {
  width: 100%;
  padding: 12px 38px 12px 13px;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  outline: none;
  background: white;
  color: #0f172a;
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}

.document-selector select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgb(37 99 235 / 8%);
}

.document-selector select:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

/* =========================
   WORKSPACE
========================= */

.workspace {
  height: calc(100vh - 190px);
  min-height: 570px;
  margin-top: 32px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
}

.workspace-body {
  height: 100%;
  min-height: 0;
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr);
}

/* =========================
   CONVERSATION SIDEBAR
========================= */

.conversation-sidebar {
  min-width: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
  background: #fbfcfe;
}

.conversation-sidebar-header {
  padding: 20px 16px 16px;
  border-bottom: 1px solid #e2e8f0;
}

.conversation-sidebar-header > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 13px;
}

.conversation-label {
  color: #334155;
  font-size: 12px;
  font-weight: 750;
}

.conversation-count {
  min-width: 22px;
  height: 22px;
  display: grid;
  place-items: center;
  padding: 0 6px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #64748b;
  font-size: 10px;
  font-weight: 700;
}

.new-chat-button {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: white;
  color: #334155;
  font: inherit;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition:
    border-color 0.2s,
    color 0.2s,
    background 0.2s;
}

.new-chat-button svg {
  width: 14px;
  height: 14px;
}

.new-chat-button:hover:not(:disabled) {
  border-color: #93c5fd;
  background: #eff6ff;
  color: #2563eb;
}

.new-chat-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.conversation-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 10px;
}

.no-conversations {
  padding: 24px 12px;
  color: #64748b;
  text-align: center;
}

.no-conversations span {
  display: block;
  font-size: 11px;
  font-weight: 700;
}

.no-conversations small {
  display: block;
  margin-top: 5px;
  color: #94a3b8;
  font-size: 10px;
  line-height: 1.5;
}

.conversation-item-wrapper {
  position: relative;
  margin-bottom: 4px;
}

.conversation-item {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 9px;
  padding: 11px 38px 11px 10px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #475569;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition:
    background 0.2s,
    color 0.2s;
}

.conversation-item:hover:not(:disabled) {
  background: #f1f5f9;
}

.conversation-item.active {
  background: #eaf2ff;
  color: #1d4ed8;
}

.conversation-item:disabled {
  cursor: default;
}

.conversation-item-icon {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  margin-top: 1px;
}

.conversation-item-icon svg {
  width: 15px;
  height: 15px;
}

.conversation-item-content {
  min-width: 0;
  display: block;
}

.conversation-title {
  display: block;
  overflow: hidden;
  color: inherit;
  font-size: 11px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conversation-document {
  display: block;
  overflow: hidden;
  margin-top: 4px;
  color: #94a3b8;
  font-size: 9px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conversation-menu-button {
  position: absolute;
  top: 9px;
  right: 7px;
  z-index: 2;
  width: 27px;
  height: 27px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  opacity: 0;
  transition:
    opacity 0.2s,
    background 0.2s,
    color 0.2s;
}

.conversation-item-wrapper:hover
  .conversation-menu-button,
.conversation-item-wrapper:focus-within
  .conversation-menu-button {
  opacity: 1;
}

.conversation-menu-button:hover:not(:disabled) {
  background: #e2e8f0;
  color: #334155;
}

.conversation-menu-button:disabled {
  cursor: default;
}

.conversation-menu-button svg {
  width: 16px;
  height: 16px;
}

.conversation-menu {
  position: absolute;
  top: 38px;
  right: 7px;
  z-index: 10;
  min-width: 112px;
  padding: 5px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  box-shadow:
    0 10px 30px rgb(15 23 42 / 12%);
}

.delete-menu-button {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 9px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #dc2626;
  font: inherit;
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
}

.delete-menu-button:hover {
  background: #fef2f2;
}

.delete-menu-button svg {
  width: 14px;
  height: 14px;
}

/* =========================
   CHAT AREA
========================= */

.chat-area {
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.conversation {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 38px;
}

.conversation-loading {
  min-height: 100%;
  display: grid;
  place-items: center;
  color: #64748b;
  font-size: 13px;
}

/* =========================
   WELCOME
========================= */

.welcome-state {
  max-width: 660px;
  margin: 55px auto 0;
  text-align: center;
}

.ai-mark {
  width: 50px;
  height: 50px;
  display: grid;
  place-items: center;
  margin: 0 auto 18px;
  border-radius: 14px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 23px;
}

.welcome-state h2 {
  margin: 0;
  font-size: 24px;
  letter-spacing: -0.035em;
}

.welcome-state > p {
  max-width: 520px;
  margin: 10px auto 27px;
  color: #64748b;
  font-size: 13px;
  line-height: 1.7;
}

.suggestions {
  display: grid;
  gap: 9px;
  text-align: left;
}

.suggestions button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  width: 100%;
  padding: 14px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: white;
  color: #334155;
  font: inherit;
  font-size: 13px;
  text-align: left;
  cursor: pointer;
  transition:
    border-color 0.2s,
    background 0.2s;
}

.suggestions button:hover {
  border-color: #bfdbfe;
  background: #f8fbff;
}

.suggestion-arrow {
  color: #94a3b8;
  font-size: 16px;
}

/* =========================
   MESSAGES
========================= */

.messages {
  width: 100%;
  max-width: 820px;
  margin: 0 auto;
}

.message {
  margin-bottom: 32px;
}

.message-user {
  display: flex;
  justify-content: flex-end;
}

.message-user-row {
  max-width: 75%;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.message-avatar {
  width: 31px;
  height: 31px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 700;
}

.user-message-avatar {
  background: #dbeafe;
  color: #2563eb;
}

.ai-message-avatar {
  background: #0f172a;
  color: white;
  font-size: 14px;
}

.message-author {
  display: block;
  margin-bottom: 7px;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
}

.user-bubble {
  padding: 12px 15px;
  border-radius: 12px 12px 3px 12px;
  background: #eff6ff;
  color: #1e3a8a;
  font-size: 13px;
  line-height: 1.65;
}

.assistant-heading {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.assistant-heading > div:last-child {
  padding-top: 2px;
}

.model-name {
  display: block;
  color: #94a3b8;
  font-size: 10px;
}

.assistant-answer {
  margin: 10px 0 0 41px;
  color: #334155;
  font-size: 14px;
  line-height: 1.8;
  white-space: pre-wrap;
}

/* =========================
   THINKING
========================= */

.thinking {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 30px;
}

.thinking-indicator {
  display: flex;
  gap: 4px;
  margin-top: 11px;
}

.thinking-indicator span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #94a3b8;
  animation: pulse 1.2s infinite ease-in-out;
}

.thinking-indicator span:nth-child(2) {
  animation-delay: 0.15s;
}

.thinking-indicator span:nth-child(3) {
  animation-delay: 0.3s;
}

@keyframes pulse {
  0%,
  80%,
  100% {
    opacity: 0.35;
    transform: scale(0.8);
  }

  40% {
    opacity: 1;
    transform: scale(1);
  }
}

/* =========================
   ERROR
========================= */

.ai-error {
  margin: 20px 0;
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 9px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 12px;
}

/* =========================
   COMPOSER
========================= */

.composer-wrapper {
  padding: 18px 28px 20px;
  border-top: 1px solid #e2e8f0;
  background: white;
}

.composer {
  max-width: 820px;
  margin: 0 auto;
  display: flex;
  align-items: flex-end;
  gap: 10px;
  padding: 8px 8px 8px 15px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: white;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.composer:focus-within {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgb(37 99 235 / 8%);
}

.composer textarea {
  width: 100%;
  min-height: 24px;
  max-height: 120px;
  resize: none;
  border: 0;
  outline: 0;
  background: transparent;
  color: #0f172a;
  font: inherit;
  font-size: 13px;
  line-height: 1.6;
}

.composer textarea::placeholder {
  color: #94a3b8;
}

.send-button {
  width: 37px;
  height: 37px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 9px;
  background: #0f172a;
  color: white;
  cursor: pointer;
  transition: background 0.2s;
}

.send-button svg {
  width: 17px;
  height: 17px;
}

.send-button:hover:not(:disabled) {
  background: #2563eb;
}

.send-button:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.composer-note {
  max-width: 820px;
  margin: 8px auto 0;
  color: #94a3b8;
  font-size: 10px;
  text-align: center;
}

/* =========================
   DELETE MODAL
========================= */

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgb(15 23 42 / 45%);
  backdrop-filter: blur(2px);
}

.delete-modal {
  width: min(100%, 420px);
  padding: 28px;
  border-radius: 15px;
  background: white;
  box-shadow:
    0 24px 70px rgb(15 23 42 / 22%);
}

.delete-modal-icon {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  margin-bottom: 17px;
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
  font-size: 19px;
  letter-spacing: -0.025em;
}

.delete-modal > p {
  margin: 9px 0 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.65;
}

.delete-conversation-name {
  overflow: hidden;
  margin-top: 17px;
  padding: 11px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  color: #334155;
  font-size: 11px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.delete-modal-error {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 11px;
}

.delete-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  margin-top: 23px;
}

.cancel-delete-button,
.confirm-delete-button {
  padding: 9px 15px;
  border-radius: 8px;
  font: inherit;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.cancel-delete-button {
  border: 1px solid #cbd5e1;
  background: white;
  color: #475569;
}

.cancel-delete-button:hover:not(:disabled) {
  background: #f8fafc;
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

.cancel-delete-button:disabled,
.confirm-delete-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

/* =========================
   EMPTY / LOADING
========================= */

.state-card,
.empty-documents {
  margin-top: 32px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: white;
}

.state-card {
  padding: 30px;
  color: #64748b;
  font-size: 13px;
}

.empty-documents {
  padding: 80px 30px;
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

.empty-documents h2 {
  margin: 0;
  font-size: 20px;
}

.empty-documents p {
  max-width: 430px;
  margin: 10px auto 20px;
  color: #64748b;
  font-size: 13px;
  line-height: 1.7;
}

.dashboard-link {
  display: inline-block;
  padding: 10px 15px;
  border-radius: 8px;
  background: #0f172a;
  color: white;
  font-size: 12px;
  font-weight: 650;
  text-decoration: none;
}

.dashboard-link:hover {
  background: #2563eb;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1100px) {
  .workspace-body {
    grid-template-columns: 210px minmax(0, 1fr);
  }
}

@media (max-width: 1000px) {
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .document-selector {
    width: 100%;
  }

  .workspace {
    height: auto;
    min-height: 650px;
  }

  .workspace-body {
    min-height: 650px;
  }
}

@media (max-width: 760px) {
  .ask-page {
    padding: 36px 20px;
  }

  .workspace {
    overflow: visible;
  }

  .workspace-body {
    display: flex;
    flex-direction: column;
  }

  .conversation-sidebar {
    max-height: 230px;
    border-right: 0;
    border-bottom: 1px solid #e2e8f0;
  }

  .conversation-list {
    max-height: 150px;
  }

  .conversation-menu-button {
    opacity: 1;
  }

  .chat-area {
    min-height: 600px;
  }

  .conversation {
    padding: 24px 18px;
  }

  .welcome-state {
    margin-top: 25px;
  }

  .message-user-row {
    max-width: 90%;
  }

  .assistant-answer {
    margin-left: 0;
  }

  .composer-wrapper {
    padding: 14px;
  }

  .delete-modal {
    padding: 22px;
  }
}
</style>