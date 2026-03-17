# mockchat-be

Laravel 12 backend (REST API) for **MockChat** — a customer service & sales training app. Provides AI-powered customer simulation, the 7-step sales flow tracker, product management, LLM provider configuration, and user/role management.

---

## Requirements

- **PHP** 8.2+
- **Composer** 2+
- **SQLite** (default, zero config) or **MySQL/MariaDB** for production
- An API key for at least one supported AI provider (optional — app falls back to a static message if none is set)

---

## First-time setup

```bash
cd mockchat-be
composer run setup
```

This single command runs: `composer install` → copy `.env` → `key:generate` → `migrate` → `npm install` → `npm run build`.

Then seed the customer types:

```bash
php artisan db:seed --class=CustomerTypeSeeder
```

---

## Running

```bash
# Start everything at once (API server + queue + log viewer + Vite)
composer run dev

# API server only
php artisan serve
```

API available at `http://localhost:8000`.

---

## Commands

| Command | Description |
|---|---|
| `composer run dev` | Start API server, queue worker, Pail log viewer, and Vite together |
| `composer run setup` | Full first-time install |
| `composer run test` | Clear config cache + run PHPUnit |
| `php artisan test --filter TestName` | Run a single test |
| `php artisan migrate` | Run database migrations |
| `php artisan db:seed --class=CustomerTypeSeeder` | Seed customer types |
| `vendor/bin/pint` | Format PHP code |

---

## Environment variables

Copy `.env.example` to `.env` and edit as needed.

### Database

```env
DB_CONNECTION=sqlite        # default; change to mysql for production
# MySQL:
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mockchat
DB_USERNAME=root
DB_PASSWORD=secret
```

### AI providers

At least one provider key is needed for real AI replies. If all are blank, the app responds with a static fallback message.

```env
CHAT_PROVIDER=groq          # default provider when no per-user setting exists
                            # options: groq | openai | anthropic | gemini | ollama

GROQ_API_KEY=               # free tier available at console.groq.com
OPENAI_API_KEY=             # platform.openai.com/api-keys
ANTHROPIC_API_KEY=          # console.anthropic.com
GEMINI_API_KEY=             # aistudio.google.com
OLLAMA_BASE_URL=http://localhost:11434   # local Ollama instance
```

### Google OAuth

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

---

## API endpoints

All routes are prefixed with `/api`. Protected routes require `Authorization: Bearer <token>`.

### Auth (public)

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/auth/register` | Register with name, email, password |
| `POST` | `/api/auth/login` | Login; returns token + user |
| `GET` | `/api/auth/google` | Get Google OAuth redirect URL |
| `GET` | `/api/auth/google/callback` | Handle Google OAuth callback |

### Auth (protected)

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/auth/me` | Current user info |
| `POST` | `/api/auth/logout` | Revoke token |

### Chat

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/chat/new` | Create conversation; returns opener message |
| `POST` | `/api/chat/send` | Send agent message; returns customer reply + suggested stage |
| `GET` | `/api/chat/messages` | Full message history (`?conversation_id=`) |
| `GET` | `/api/chat/conversation` | Conversation metadata (`?conversation_id=`) |
| `POST` | `/api/chat/end` | Close a conversation |
| `GET` | `/api/chat/stats` | Global counts (total conversations, messages, by type) |

### LLM settings

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/llm/providers` | List all available providers |
| `GET` | `/api/llm/settings` | Current user's saved provider keys |
| `POST` | `/api/llm/settings` | Save an API key for a provider |
| `DELETE` | `/api/llm/settings/{provider}` | Remove a provider key |

### Products

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/products` | List user's products |
| `POST` | `/api/products` | Create a product |
| `GET` | `/api/products/{id}` | Get a product |
| `PUT/PATCH` | `/api/products/{id}` | Update a product |
| `DELETE` | `/api/products/{id}` | Delete a product |

### Admin (`role:admin`)

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/admin/users` | List all users |
| `PATCH` | `/api/admin/users/{user}` | Update user (role, etc.) |
| `POST` | `/api/admin/users/{user}/toggle` | Enable / disable a user |

### Mentor (`role:admin,mentor`)

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/mentor/users` | List students |
| `POST` | `/api/mentor/students/{user}/toggle` | Enable / disable a student |
| `GET` | `/api/mentor/students/{user}/conversations` | Student's conversations |
| `GET` | `/api/mentor/students/{user}/conversations/{id}/messages` | Messages in a conversation |
| `GET` | `/api/mentor/students/{user}/products` | Student's products |
| `POST` | `/api/mentor/students/{user}/products` | Add product for a student |
| `DELETE` | `/api/mentor/students/{user}/products/{productId}` | Remove student product |
| `GET` | `/api/mentor/students/{user}/llm` | Student's LLM settings |
| `POST` | `/api/mentor/students/{user}/llm` | Add LLM setting for a student |
| `DELETE` | `/api/mentor/students/{user}/llm/{provider}` | Remove student LLM setting |

---

## Architecture

### Request flow (chat message)

1. `ChatController::sendMessage` saves the agent's message and fetches the full conversation history.
2. Resolves the correct `ChatServiceInterface` implementation — first checks the user's `UserLlmSetting`, then falls back to the `CHAT_PROVIDER` env var.
3. Calls the selected service (e.g. `GroqChatService`) to get a Tagalog customer reply.
4. Saves the customer reply, runs `ChatStageDetection::fromAgentMessages` (keyword-regex over cumulative agent text) to detect the current stage (1–7).
5. Returns `{ agent_message, customer_response, suggested_stage }`.

### AI providers

All providers implement `ChatServiceInterface`. Each falls back to a static Tagalog message if the API key is missing or the call fails.

| Class | Provider | Notes |
|---|---|---|
| `GroqChatService` | Groq | Free tier, fast; default |
| `OpenAIChatService` | OpenAI | `gpt-4o-mini` |
| `AnthropicChatService` | Anthropic | Claude |
| `GeminiChatService` | Google Gemini | |
| `OllamaChatService` | Ollama | Local; set `OLLAMA_BASE_URL` |

The provider is selected per-request: if the user has a saved `UserLlmSetting` for the requested provider it takes precedence; otherwise the global `CHAT_PROVIDER` env var is used.

### Key classes

| Class | Location | Purpose |
|---|---|---|
| `ChatController` | `Http/Controllers/Api/` | All chat endpoints |
| `AuthController` | `Http/Controllers/Api/` | Register, login, Google OAuth |
| `ProductController` | `Http/Controllers/Api/` | CRUD products |
| `LlmSettingsController` | `Http/Controllers/Api/` | Manage per-user LLM keys |
| `UserController` | `Http/Controllers/Api/` | Admin + mentor user management |
| `ChatStageDetection` | `Services/` | Keyword-regex 7-step stage detector |
| `EnsureRole` | `Http/Middleware/` | Role-based route guard |
| `CheckEnabled` | `Http/Middleware/` | Blocks disabled user accounts |

### Models

| Model | Table | Key fields |
|---|---|---|
| `User` | `users` | `name`, `email`, `role` (student/mentor/admin), `is_enabled`, `google_id` |
| `Conversation` | `conversations` | `user_id`, `customer_type_id`, `product_id`, `customer_name`, `status` |
| `Message` | `messages` | `conversation_id`, `sender` (agent\|customer), `body` |
| `CustomerType` | `customer_types` | `type_key`, `label`, `personality` |
| `Product` | `products` | `user_id`, `name`, `description`, `price` |
| `UserLlmSetting` | `user_llm_settings` | `user_id`, `provider`, `api_key`, `is_default` |

### 7-step sales flow

`ChatStageDetection` scans cumulative agent message text with keyword regexes (English + Tagalog) to return the current stage (1–7):

1. Greeting / Rapport
2. Probing
3. Empathize / Acknowledge
4. Solution
5. Magnifying the Value
6. Offer / Close
7. Confirmation / Next Steps

### Customer types

Seeded via `CustomerTypeSeeder`. Thirteen types are available: `normal_buyer`, `irate_returner`, `irate_annoyed`, `confused`, `impatient`, `friendly`, `skeptical`, `demanding`, `indecisive`, `bargain_hunter`, `loyal`, `first_time_buyer`, `silent`.

---

## Database

SQLite is the default (no setup required). For MySQL:

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE mockchat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then update `.env`:

```env
DB_CONNECTION=mysql
DB_DATABASE=mockchat
DB_USERNAME=root
DB_PASSWORD=your_password
```

Run migrations:

```bash
php artisan migrate
php artisan db:seed --class=CustomerTypeSeeder
```
