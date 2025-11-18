# AI Hero Assistant - WordPress Plugin

An advanced WordPress plugin that adds an AI chatbot with animated chip in the hero section, integrated with Google Gemini API.

## Features

- 🤖 **Animated Abstract Chip** - Particle system that forms an abstract humanoid chip with speaking mouth
- 💬 **AI Chatbot** - Full integration with Google Gemini API
- 🌍 **Multilingual** - Automatic language detection and responses in user's language
- 📝 **Typing Effect** - Animated subtitle with character-by-character typing effect
- 🎨 **Customizable** - Gradient colors, fonts and configurable messages from admin
- 📊 **Lead Generation** - Automatic email/phone capture from conversations
- 💾 **Database** - Save conversations and leads in WordPress database
- 📱 **Responsive** - Fully responsive design for all devices

## Installation

1. Copy the `ai-hero-assistant` folder to the `wp-content/plugins/` directory of your WordPress site
2. Activate the plugin from WordPress admin panel (Plugins → Installed Plugins)
3. Go to Settings → AI Hero Assistant for configuration

## Configuration

### 1. Get Gemini API Key

1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Create an account or sign in
3. Generate a new API key
4. Copy the key into the "Google Gemini API Key" field in the plugin settings

### 2. Configure Settings

In the settings page (`Settings → AI Hero Assistant`):

- **API Key**: Enter your Gemini API key
- **Model**: Select Gemini model (Flash, Pro, etc.)
- **Company Name**: Your company name
- **Initial Hero Message**: Message displayed on page load (use `{company_name}` for name)
- **AI Instructions**: Detailed instructions for AI behavior
- **Documentation**: Upload PDF/DOC/TXT files with service information
- **Gradient Colors**: Select colors for gradient
- **Font Family**: Choose font for text

### 3. Add Shortcode to Page

Add the `[ai_hero_assistant]` shortcode to your home page or hero section:

```php
[ai_hero_assistant height="600px"]
```

Or in the page editor:
- Add a "Shortcode" block
- Enter: `[ai_hero_assistant]`

## Usage

### Shortcode

```
[ai_hero_assistant]
```

Optional parameters:
- `height` - Hero section height (e.g., "600px", "80vh")

### Lead Generation

The plugin automatically detects emails and phone numbers from user conversations and saves them to the database. You can view captured leads in the settings page.

## File Structure

```
ai-hero-assistant/
├── ai-hero-assistant.php    # Main plugin file
├── includes/
│   ├── class-database.php    # Database management
│   ├── class-gemini-api.php # Gemini API integration
│   ├── class-shortcode.php  # Shortcode handler
│   ├── class-admin-settings.php # Admin settings page
│   └── class-ajax-handler.php  # AJAX request handler
├── assets/
│   ├── css/
│   │   ├── frontend.css      # Frontend styles
│   │   └── admin.css         # Admin styles
│   └── js/
│       ├── frontend.js       # Frontend JavaScript (particles, typing effect)
│       └── admin.js          # Admin JavaScript
└── README.md                 # This file
```

## Database

The plugin creates the following tables:

- `wp_aiha_conversations` - Conversations (session_id, user_ip, etc.)
- `wp_aiha_messages` - Individual messages from conversations
- `wp_aiha_leads` - Captured leads (email, phone, name)

## Requirements

- WordPress 5.0 or newer
- PHP 7.4 or newer
- Google Gemini API key
- jQuery (included in WordPress)

## Support

For issues or questions, contact the development team.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Animated chip with particles
- Gemini API integration
- Lead generation
- Admin settings page
- Responsive design
