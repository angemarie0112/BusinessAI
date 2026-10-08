import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useAIStore = defineStore('ai', () => {
  const asking = ref(false)
  const loadingConversation = ref(false)
  const creatingConversation = ref(false)
  const deletingConversation = ref(false)

  const error = ref(null)

  const conversations = ref([])
  const currentConversation = ref(null)

  /**
   * Get all conversations belonging to the
   * authenticated user.
   */
  const fetchConversations = async () => {
    error.value = null

    try {
      const response = await api.get('/conversations')

      conversations.value =
        response.data.conversations ?? []

      return {
        success: true,
        data: conversations.value,
      }
    } catch (requestError) {
      error.value =
        requestError.response?.data?.message ||
        'Could not load conversations.'

      return {
        success: false,
        data: [],
      }
    }
  }

  /**
   * Create a new conversation for a document.
   */
  const createConversation = async (documentId) => {
    creatingConversation.value = true
    error.value = null

    try {
      const response = await api.post(
        `/documents/${documentId}/conversations`,
      )

      const conversation =
        response.data.conversation

      currentConversation.value = conversation

      /*
       * Add the newly created conversation to the
       * beginning of the local list.
       */
      conversations.value = [
        conversation,
        ...conversations.value.filter(
          (item) => item.id !== conversation.id,
        ),
      ]

      return {
        success: true,
        data: conversation,
      }
    } catch (requestError) {
      error.value =
        requestError.response?.data?.message ||
        'Could not create a conversation.'

      return {
        success: false,
        data: null,
      }
    } finally {
      creatingConversation.value = false
    }
  }

  /**
   * Load one conversation together with all of its
   * saved messages.
   */
  const fetchConversation = async (conversationId) => {
    loadingConversation.value = true
    error.value = null

    try {
      const response = await api.get(
        `/conversations/${conversationId}`,
      )

      currentConversation.value =
        response.data.conversation

      return {
        success: true,
        data: currentConversation.value,
      }
    } catch (requestError) {
      error.value =
        requestError.response?.data?.message ||
        'Could not load the conversation.'

      currentConversation.value = null

      return {
        success: false,
        data: null,
      }
    } finally {
      loadingConversation.value = false
    }
  }

  /**
   * Delete one saved conversation.
   *
   * Laravel also deletes all messages belonging to
   * the conversation through the database cascade.
   */
  const deleteConversation = async (conversationId) => {
    deletingConversation.value = true
    error.value = null

    try {
      await api.delete(
        `/conversations/${conversationId}`,
      )

      /*
       * Remove the deleted conversation from the
       * local history immediately.
       */
      conversations.value =
        conversations.value.filter(
          (conversation) =>
            String(conversation.id) !==
            String(conversationId),
        )

      /*
       * If the deleted conversation is currently
       * loaded, clear it from the store as well.
       */
      if (
        String(currentConversation.value?.id) ===
        String(conversationId)
      ) {
        currentConversation.value = null
      }

      return {
        success: true,
      }
    } catch (requestError) {
      error.value =
        requestError.response?.data?.message ||
        'Could not delete the conversation.'

      return {
        success: false,
      }
    } finally {
      deletingConversation.value = false
    }
  }

  /**
   * Ask BusinessAI a question about one document.
   *
   * Conversation history is no longer sent by Vue.
   * Laravel loads the trusted history from ai_messages.
   */
  const askDocument = async (
    documentId,
    conversationId,
    question,
  ) => {
    asking.value = true
    error.value = null

    try {
      const response = await api.post(
        `/documents/${documentId}/ask`,
        {
          question,
          conversation_id: conversationId,
        },
      )

      return {
        success: true,
        data: response.data,
      }
    } catch (requestError) {
      error.value =
        requestError.response?.data?.message ||
        'BusinessAI could not answer your question.'

      return {
        success: false,
        data: null,
      }
    } finally {
      asking.value = false
    }
  }

  /**
   * Clear the currently selected conversation.
   */
  const clearCurrentConversation = () => {
    currentConversation.value = null
  }

  const clearError = () => {
    error.value = null
  }

  return {
    asking,
    loadingConversation,
    creatingConversation,
    deletingConversation,
    error,
    conversations,
    currentConversation,

    fetchConversations,
    createConversation,
    fetchConversation,
    deleteConversation,
    askDocument,
    clearCurrentConversation,
    clearError,
  }
})