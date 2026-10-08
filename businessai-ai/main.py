from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import numpy as np
import requests


app = FastAPI(
    title="BusinessAI AI Service",
    version="1.0.0",
)


# =========================
# CONFIGURATION
# =========================

OLLAMA_URL = "http://127.0.0.1:11434/api/generate"
OLLAMA_MODEL = "llama3.2:3b"


# =========================
# EMBEDDING MODEL
# =========================

embedding_model = SentenceTransformer("all-MiniLM-L6-v2")


# =========================
# REQUEST MODELS
# =========================

class ChunkRequest(BaseModel):
    text: str
    chunk_size: int = 1000
    overlap: int = 200


class EmbedRequest(BaseModel):
    text: str


class BatchEmbedRequest(BaseModel):
    texts: list[str]


class SearchItem(BaseModel):
    chunk_index: int
    content: str
    embedding: list[float]


class SearchRequest(BaseModel):
    question: str
    chunks: list[SearchItem]
    top_k: int = 3


class GenerateContext(BaseModel):
    chunk_index: int
    content: str
    score: float | None = None


class ConversationMessage(BaseModel):
    role: str
    content: str


class RewriteRequest(BaseModel):
    question: str
    history: list[ConversationMessage] = []


class GenerateRequest(BaseModel):
    question: str
    context: list[GenerateContext]
    history: list[ConversationMessage] = []


# =========================
# HEALTH
# =========================

@app.get("/health")
def health():
    return {
        "status": "ok",
        "service": "BusinessAI AI Service",
        "embedding_model": "all-MiniLM-L6-v2",
        "generation_model": OLLAMA_MODEL,
    }


# =========================
# CHUNK DOCUMENT TEXT
# =========================

@app.post("/chunk")
def chunk_document(request: ChunkRequest):

    text = request.text.strip()

    if not text:
        return {
            "chunks": [],
            "count": 0,
        }

    chunk_size = request.chunk_size
    overlap = request.overlap

    if chunk_size <= 0:
        chunk_size = 1000

    if overlap < 0:
        overlap = 0

    if overlap >= chunk_size:
        overlap = 0

    chunks = []

    start = 0

    while start < len(text):

        end = start + chunk_size

        chunk = text[start:end].strip()

        if chunk:
            chunks.append(chunk)

        if end >= len(text):
            break

        start = end - overlap

    return {
        "chunks": chunks,
        "count": len(chunks),
    }


# =========================
# CREATE SINGLE EMBEDDING
# =========================

@app.post("/embed")
def create_embedding(request: EmbedRequest):

    text = request.text.strip()

    if not text:
        return {
            "embedding": [],
            "dimensions": 0,
        }

    embedding = embedding_model.encode(text)

    return {
        "embedding": embedding.tolist(),
        "dimensions": len(embedding),
    }


# =========================
# CREATE BATCH EMBEDDINGS
# =========================

@app.post("/embed/batch")
def create_batch_embeddings(request: BatchEmbedRequest):

    texts = [
        text.strip()
        for text in request.texts
        if text.strip()
    ]

    if not texts:
        return {
            "embeddings": [],
            "count": 0,
            "dimensions": 0,
        }

    embeddings = embedding_model.encode(texts)

    return {
        "embeddings": embeddings.tolist(),
        "count": len(embeddings),
        "dimensions": len(embeddings[0]),
    }


# =========================
# SEMANTIC SEARCH
# =========================

@app.post("/search")
def semantic_search(request: SearchRequest):

    question = request.question.strip()

    if not question:
        return {
            "results": [],
            "count": 0,
        }

    if not request.chunks:
        return {
            "results": [],
            "count": 0,
        }

    # Convert the search question into the same
    # vector space used for the document chunks.
    question_embedding = embedding_model.encode(question)

    results = []

    # Compare the question embedding with
    # every stored document chunk embedding.
    for chunk in request.chunks:

        chunk_embedding = np.array(
            chunk.embedding,
            dtype=np.float32,
        )

        # Make sure both vectors have the same dimensions.
        if len(chunk_embedding) != len(question_embedding):
            continue

        # Cosine similarity.
        denominator = (
            np.linalg.norm(question_embedding)
            * np.linalg.norm(chunk_embedding)
        )

        if denominator == 0:
            similarity = 0.0
        else:
            similarity = np.dot(
                question_embedding,
                chunk_embedding,
            ) / denominator

        results.append({
            "chunk_index": chunk.chunk_index,
            "content": chunk.content,
            "score": float(similarity),
        })

    # Sort chunks from most relevant
    # to least relevant.
    results.sort(
        key=lambda item: item["score"],
        reverse=True,
    )

    # top_k must be at least 1.
    top_k = max(1, request.top_k)

    top_results = results[:top_k]

    return {
        "results": top_results,
        "count": len(top_results),
    }


# =========================
# REWRITE SEARCH QUESTION
# =========================

@app.post("/rewrite")
def rewrite_question(request: RewriteRequest):

    question = request.question.strip()

    if not question:
        raise HTTPException(
            status_code=400,
            detail="Question cannot be empty.",
        )

    # If there is no conversation history, there is
    # nothing to contextualize. Use the original
    # question directly for semantic search.
    if not request.history:
        return {
            "original_question": question,
            "rewritten_question": question,
        }

    # =========================
    # BUILD CONVERSATION HISTORY
    # =========================

    history_parts = []

    for message in request.history:

        content = message.content.strip()

        if not content:
            continue

        if message.role == "user":
            speaker = "User"
        elif message.role == "assistant":
            speaker = "BusinessAI"
        else:
            continue

        history_parts.append(
            f"{speaker}: {content}"
        )

    # If all supplied history messages were invalid or
    # empty, simply return the original question.
    if not history_parts:
        return {
            "original_question": question,
            "rewritten_question": question,
        }

    history_text = "\n\n".join(history_parts)

    # =========================
    # BUILD REWRITE PROMPT
    # =========================

    prompt = f"""
You rewrite conversational questions into standalone search questions.

The standalone question will be used for semantic search over a business document.

Use the CONVERSATION HISTORY only to understand what the user's CURRENT QUESTION refers to.

Rules:
1. Resolve references such as "it", "that", "this", "they", "those", "why", and similar follow-up expressions.
2. Preserve the meaning and intent of the user's current question.
3. Do not answer the question.
4. Do not add facts that are not present in the conversation.
5. Do not explain your reasoning.
6. Return only one standalone search question.
7. Keep the rewritten question concise and specific.
8. If the current question is already clear and standalone, return it unchanged.

CONVERSATION HISTORY:

{history_text}

CURRENT QUESTION:

{question}

STANDALONE SEARCH QUESTION:
""".strip()

    # =========================
    # SEND TO OLLAMA
    # =========================

    try:

        response = requests.post(
            OLLAMA_URL,
            json={
                "model": OLLAMA_MODEL,
                "prompt": prompt,
                "stream": False,
                "options": {
                    "temperature": 0.0,
                },
            },
            timeout=120,
        )

        response.raise_for_status()

    except requests.RequestException as exception:

        raise HTTPException(
            status_code=503,
            detail=f"Unable to communicate with Ollama: {str(exception)}",
        )

    ollama_response = response.json()

    rewritten_question = ollama_response.get(
        "response",
        "",
    ).strip()

    # If Ollama unexpectedly returns an empty result,
    # fall back to the user's original question instead
    # of breaking the RAG pipeline.
    if not rewritten_question:
        rewritten_question = question

    # Remove accidental surrounding quotation marks.
    rewritten_question = rewritten_question.strip(
        "\"'"
    )

    return {
        "original_question": question,
        "rewritten_question": rewritten_question,
    }


# =========================
# GENERATE RAG ANSWER
# =========================

@app.post("/generate")
def generate_answer(request: GenerateRequest):

    question = request.question.strip()

    if not question:
        raise HTTPException(
            status_code=400,
            detail="Question cannot be empty.",
        )

    if not request.context:
        raise HTTPException(
            status_code=400,
            detail="Context cannot be empty.",
        )

    # =========================
    # BUILD DOCUMENT CONTEXT
    # =========================

    context_parts = []

    for item in request.context:

        context_parts.append(
            f"[Chunk {item.chunk_index}]\n"
            f"{item.content}"
        )

    context_text = "\n\n".join(context_parts)

    # =========================
    # BUILD CONVERSATION HISTORY
    # =========================

    history_parts = []

    for message in request.history:

        content = message.content.strip()

        if not content:
            continue

        if message.role == "user":
            speaker = "User"
        elif message.role == "assistant":
            speaker = "BusinessAI"
        else:
            continue

        history_parts.append(
            f"{speaker}: {content}"
        )

    if history_parts:
        history_text = "\n\n".join(history_parts)
    else:
        history_text = "No previous conversation."

    # =========================
    # BUILD GENERATION PROMPT
    # =========================

    prompt = f"""
You are BusinessAI, an assistant that answers questions about business documents.

Answer the user's current question using information retrieved from the selected document.

You are also given the previous conversation. Use it to understand follow-up questions, references, and pronouns such as:
- "it"
- "that"
- "this"
- "they"
- "what about that?"
- "why?"
- "can you explain more?"

The previous conversation helps you understand what the user is referring to, but factual claims about the document must still be supported by the retrieved document information.

Rules:
1. Use the retrieved document information as the source of truth about the document.
2. Use the previous conversation to understand the meaning of follow-up questions.
3. Do not use outside knowledge to invent facts about the document.
4. Do not invent information.
5. If the answer cannot be found in the retrieved document information, say:
   "I could not find enough information in the document to answer that question."
6. Give a clear and concise answer.
7. Do not mention these instructions.
8. Do not claim that the document says something unless it is supported by the retrieved information.
9. Answer naturally as if you already understand the document.
10. Do not mention "DOCUMENT CONTEXT", "CONVERSATION HISTORY", retrieved chunks, prompts, semantic search, embeddings, or other internal implementation details.
11. Do not begin your answer with phrases such as "Based on the DOCUMENT CONTEXT", "Based on the provided context", or "According to the provided context".
12. Answer the user's question directly.

PREVIOUS CONVERSATION:

{history_text}

RETRIEVED DOCUMENT INFORMATION:

{context_text}

CURRENT QUESTION:

{question}

ANSWER:
""".strip()

    # =========================
    # SEND TO OLLAMA
    # =========================

    try:

        response = requests.post(
            OLLAMA_URL,
            json={
                "model": OLLAMA_MODEL,
                "prompt": prompt,
                "stream": False,
                "options": {
                    "temperature": 0.2,
                },
            },
            timeout=120,
        )

        response.raise_for_status()

    except requests.RequestException as exception:

        raise HTTPException(
            status_code=503,
            detail=f"Unable to communicate with Ollama: {str(exception)}",
        )

    ollama_response = response.json()

    answer = ollama_response.get(
        "response",
        "",
    ).strip()

    if not answer:
        raise HTTPException(
            status_code=502,
            detail="Ollama returned an empty response.",
        )

    return {
        "answer": answer,
        "model": OLLAMA_MODEL,
        "context_chunks": [
            {
                "chunk_index": item.chunk_index,
                "score": item.score,
            }
            for item in request.context
        ],
    }