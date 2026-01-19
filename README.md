# AI Support Bot

A scalable AI Support Assistant built with Laravel 12 with **29 production-ready API endpoints**.

## 🚀 Features

-   **Multi-Format Document Processing**: PDF, DOCX, TXT with parallel batch processing
-   **Intelligent Vector Search**: OpenAI embeddings with automatic storage optimization (JSON/pgvector/Qdrant)
-   **Conversation Memory**: Context-aware follow-up questions with streaming responses (SSE)
-   **Quota Management**: Track usage, messages, and token consumption
-   **Multi-Tenant Workspaces**: Isolated environments with filtering
-   **Production Ready**: Laravel Sanctum auth, Redis queues, rate limiting

## 🏗️ Tech Stack 

-   **Framework**: Laravel 12 (PHP 8.4)
-   **AI Model**: GPT-4o-mini (chat completions)
-   **Embeddings**: text-embedding-3-small (1536 dimensions)
-   **Database**: MariaDB/MySQL (JSON vector storage) or PostgreSQL (pgvector support)
-   **Cache & Queue**: Redis (Required for batch processing)
-   **Authentication**: Laravel Sanctum with API tokens
-   **Vector Storage**: Auto-detecting (JSON/pgvector/Qdrant)



## Installation 

1.  **Clone & Install**
    ```bash
    git clone https://github.com/3idey/ai-support-bot.git
    cd ai-support-bot
    composer install
    ```

2.  **Environment Setup**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    Configure your `.env` file:
    ```env
    OPENAI_API_KEY=sk-...
    DB_CONNECTION=mariadb
    QUEUE_CONNECTION=redis
    CACHE_DRIVER=redis
    ```

3.  **Database & Migration**
    ```bash
    touch database/database.sqlite # If using SQLite
    php artisan migrate
    ```

4.  **Start Services**
    Ensure Redis is running:
    ```bash
    redis-server
    ```

    Start the queue worker (Critical for batch processing):
    ```bash
    php artisan queue:work
    ```

    Start the dev server:
    ```bash
    php artisan serve
    ```

## API Documentation

### Authentication
All API endpoints require a Bearer token:
```bash
Authorization: Bearer <your-token>
```
Generate a token: `php artisan tinker` → `User::first()->createToken('dev')->plainTextToken`

---

### 🎯 Quota Management (8 Endpoints)

Monitor and track user usage limits.

#### Check Full Quota Status
```bash
curl http://localhost:8000/api/quota \
  -H "Authorization: Bearer <token>"
```
**Response:**
```json
{
  "message_quota": { "total": 50, "used": 15, "remaining": 35, "percentage": 30 },
  "token_quota": { "total": 100000, "used": 12450, "remaining": 87550, "percentage": 12.45 },
  "document_quota": { "total": 100, "used": 5, "remaining": 95, "percentage": 5 }
}
```

#### Other Quota Endpoints
- `GET /api/quota/check` - Simple boolean check
- `GET /api/quota/remaining` - Get remaining messages
- `GET /api/quota/tokens` - Get remaining tokens
- `GET /api/quota/usage` - Detailed usage breakdown
- `POST /api/quota/track` - Manually track token usage
- `GET /api/quota/documents` - Check document quota
- `POST /api/quota/documents/validate` - Validate document size

---

### 🗂️ Workspace Management (7 Endpoints)

Multi-tenant workspace isolation and filtering.

#### List Workspaces
```bash
curl http://localhost:8000/api/workspaces \
  -H "Authorization: Bearer <token>"
```

#### Create Workspace
```bash
curl -X POST http://localhost:8000/api/workspaces \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Customer Support", "description": "Support documentation"}'
```

#### Other Workspace Endpoints
- `GET /api/workspaces/{id}` - Show workspace details
- `PUT /api/workspaces/{id}` - Update workspace
- `DELETE /api/workspaces/{id}` - Delete workspace
- `GET /api/workspaces/{id}/documents` - List workspace documents
- `GET /api/workspaces/{id}/conversations` - List workspace conversations

---

### 💬 Conversation Management (4 Endpoints)

Manage chat history and conversations.

#### List Conversations
```bash
curl http://localhost:8000/api/conversations?workspace_id=1 \
  -H "Authorization: Bearer <token>"
```

#### Get Conversation Messages
```bash
curl http://localhost:8000/api/conversations/1/messages \
  -H "Authorization: Bearer <token>"
```

#### Other Conversation Endpoints
- `GET /api/conversations/{id}` - Show conversation details
- `DELETE /api/conversations/{id}` - Delete conversation

---

### 📄 Document Management (4 Endpoints)

Upload and manage knowledge base documents with filtering.

#### List Documents (with filtering)
```bash
curl http://localhost:8000/api/documents?workspace_id=1 \
  -H "Authorization: Bearer <token>"
```

#### Upload Document
```bash
curl -X POST http://localhost:8000/api/documents \
  -H "Authorization: Bearer <token>" \
  -F "file=@manual.pdf" \
  -F "workspace_id=1"
```

#### Other Document Endpoints
- `GET /api/documents/{id}` - Show document details
- `DELETE /api/documents/{id}` - Delete document

---

### 🤖 Chat Endpoints (1 Endpoint)

#### Ask Question (JSON Response)
```bash
curl -X POST http://localhost:8000/api/ask \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "How do I reset my password?",
    "workspace_id": 1
  }'
```

#### Ask with Streaming + Memory
```bash
curl -N -X POST http://localhost:8000/api/ask \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "Tell me more about that.",
    "conversation_id": 1,
    "stream": true
  }'
```
**Stream Events:**
- `event: sources` - Related document chunks
- `event: conversation_id` - ID for follow-ups
- `data: {"content": "..."}` - Answer tokens
- `data: [DONE]` - Stream complete

---

### 📊 Vector Database Management (4 Endpoints)

Intelligent vector storage with automatic optimization recommendations.

#### Check Vector Database Status
```bash
curl http://localhost:8000/api/vectors/status \
  -H "Authorization: Bearer <token>"
```
**Response:**
```json
{
  "system": "mariadb_json",
  "status": "active",
  "total_embeddings": 150,
  "avg_dimensions": 1536,
  "database": "MariaDB 11.6.2",
  "storage_method": "JSON columns with PHP cosine similarity"
}
```

#### Get Optimization Recommendations
```bash
curl http://localhost:8000/api/vectors/recommendations \
  -H "Authorization: Bearer <token>"
```

#### Performance Benchmark
```bash
curl -X POST http://localhost:8000/api/vectors/benchmark \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"sample_size": 10}'
```

#### Migrate to pgvector (PostgreSQL only)
```bash
curl -X POST http://localhost:8000/api/vectors/migrate \
  -H "Authorization: Bearer <token>"
```

---

### 🏥 Health Check
```bash
curl http://localhost:8000/api/health
```


---



## 🚀 Performance & Scalability

### Benchmarks
- **Vector Search**: ~30ms (JSON), ~5ms (pgvector), ~1ms (Qdrant)
- **API Response**: <100ms average
- **Scaling**: Stateless design with horizontal scaling support


---


## 📄 License

MIT License
