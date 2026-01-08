# AI Support Bot

A powerful, scalable, and memory-aware AI Support Assistant built with Laravel 12.

## Features 

-   **Multi-Format Ingestion**: Process PDF, DOCX, and TXT files with automatic text extraction.
-   **Vector Search**: Smart retrieval using OpenAI embeddings and cosine similarity.
-   **Conversation Memory**: Remembers context from previous messages for natural follow-up questions.
-   **Streaming Responses**: Real-time typewriter effect using Server-Sent Events (SSE).
-   **Scalable**: Memory-optimized search algorithm (O(1) memory usage) for large datasets.
-   **Secure**: API endpoints protected by Laravel Sanctum authentication and rate limiting.
-   **Robust**: Resilient background job processing with detailed error tracking and status updates.

## Tech Stack 

-   **Framework**: Laravel 12 (PHP 8.4)
-   **AI Model**: GPT-4o-mini
-   **Embeddings**: text-embedding-3-small
-   **Database**: SQLite (Default) / MySQL / PostgreSQL compatible
-   **Queue System**: Database / Redis

## Installation 

1.  **Clone & Install**
    ```bash
    git clone https://github.com/oh3idx/ai-support-bot.git
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
    DB_CONNECTION=sqlite # or mysql
    ```

3.  **Database & Migration**
    ```bash
    touch database/database.sqlite # If using SQLite
    php artisan migrate
    ```

4.  **Start Services**
    Start the dev server:
    ```bash
    php artisan serve
    ```
    Start the queue worker (for document processing):
    ```bash
    php artisan queue:work
    ```

## API Documentation 

### Authentication
All API endpoints require a Bearer token.
```bash
Authorization: Bearer <your-token>
```
*Note: You can generate a token using `App\Models\User::first()->createToken('dev')->plainTextToken` in tinker.*

### 1. Upload Document
Upload a document to be indexed.
**POST** `/documents/upload`
```bash
curl -X POST http://localhost:8000/documents/upload \
  -H "Accept: application/json" \
  -F "file=@/path/to/manual.pdf" \
  -F "workspace_id=1"
```

### 2. Chat (JSON)
Standard Request-Response chat.
**POST** `/api/ask`
```bash
curl -X POST http://localhost:8000/api/ask \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "How do I reset my password?",
    "workspace_id": 1
  }'
```

### 3. Chat (Streaming + Memory)
Enable streaming and pass `conversation_id` for context.
**POST** `/api/ask`
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
**Response Stream Events:**
- `event: sources` (Related document chunks)
- `event: conversation_id` (ID for follow-ups)
- `data: {"content": "..."}` (Answer tokens)
- `data: [DONE]`

## License 

MIT License.
