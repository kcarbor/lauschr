# LauschR 🎙️

A multi-user podcast feed management platform with collaborative features. Create personal feeds, invite collaborators, and manage podcast content with ease.

## Features

- **Multi-User System**: Each user has their own account with personal feeds
- **Collaborative Feeds**: Invite collaborators with role-based permissions (Owner/Editor/Viewer)
- **Episode Management**: Upload, edit, and delete podcast episodes
- **RSS Feed Generation**: Automatic RSS 2.0 feed generation with iTunes extensions
- **Clean UI**: Modern, responsive interface in German
- **Secure**: Session-based auth, CSRF protection, input validation

## Architecture

```
lauschr/
├── config/
│   └── config.php              # Application configuration
├── data/                       # JSON data storage (gitignored)
│   ├── users.json              # User accounts
│   ├── feeds/                  # Feed data (one JSON per feed)
│   └── audio/                  # Audio files organized by feed
├── public/                     # Web root
│   ├── index.php               # Front controller
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/app.js
│   └── .htaccess               # Apache config with URL rewriting
├── src/
│   ├── Auth/                   # Session & password handling
│   ├── Core/                   # App, Router, View
│   ├── Feed/                   # RSS generator
│   ├── Models/                 # User, Feed, Episode, Permission
│   ├── Security/               # CSRF, Validator
│   └── Storage/                # JSON file storage with locking
└── templates/                  # PHP templates
    ├── layout.php              # Base layout
    ├── auth/                   # Login, register
    ├── feed/                   # Feed management views
    └── errors/                 # Error pages
```

## Requirements

- PHP 8.1+
- Apache with mod_rewrite
- Write permissions for `data/` directory

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/kcarbor/lauschr.git
   cd lauschr
   ```

2. Configure your web server to point to the `public/` directory

3. Copy and customize the configuration:
   ```bash
   # Set your app URL in config/config.php or via environment variables
   export APP_URL="https://your-domain.com"
   export APP_DEBUG=false
   ```

4. Ensure the `data/` directory is writable:
   ```bash
   chmod -R 755 data/
   ```

5. Visit your domain and register the first user account

## Configuration

Configuration is managed in `config/config.php`. Key settings:

| Setting | Description | Default |
|---------|-------------|---------|
| `app.url` | Base URL of the application | `http://localhost` |
| `app.debug` | Enable debug mode | `false` |
| `upload.max_file_size` | Maximum audio file size | `200 MB` |
| `session.lifetime` | Session duration | `24 hours` |

Environment variables can override config values:
- `APP_URL`
- `APP_DEBUG`
- `SESSION_SECURE`

## Permissions

| Role | View | Upload | Edit | Delete | Settings | Invite |
|------|------|--------|------|--------|----------|--------|
| **Owner** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Editor** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Viewer** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

## API (Future)

The architecture is designed to support a future REST API for AI agent integration. Key endpoints will include:

- `POST /api/feeds` - Create feed
- `GET /api/feeds/{id}` - Get feed details
- `POST /api/feeds/{id}/episodes` - Upload episode
- `GET /api/feeds/{id}/episodes` - List episodes

## Development

```bash
# Start PHP development server
php -S localhost:8000 -t public/

# Watch for file changes (optional)
# Use your preferred tool
```

## Security Features

- **Session Security**: HTTP-only, secure cookies with strict same-site policy
- **CSRF Protection**: Token-based protection for all forms
- **Password Hashing**: Argon2id (or bcrypt fallback)
- **Input Validation**: Server-side validation for all user input
- **File Upload Security**: MIME type validation, extension checking

## License

MIT

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

---

*Built for the RAIME research group*
