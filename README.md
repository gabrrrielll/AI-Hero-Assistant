# AI Hero Assistant - WordPress Plugin

An advanced WordPress plugin that adds an AI chatbot with dual video overlay avatar in the hero section, integrated with Google Gemini API.

## Features

- 🤖 **Animated Video Avatar** - Two overlapping videos that alternate based on AI speaking state
- 💬 **AI Chatbot** - Full integration with Google Gemini API
- 🌍 **Multilingual** - Automatic language detection and responses in user's language
- 📝 **Typing Effect** - Animated subtitle with character-by-character typing effect
- 🎨 **Customizable** - Gradient colors, fonts and configurable messages from admin
- 📊 **Lead Generation** - Automatic email/phone capture from conversations
- 📧 **Email Notifications** - Automatic email notifications when leads are captured
- 🔊 **Voice Synthesis** - Text-to-speech support for AI responses
- 💾 **Database** - Save conversations and leads in WordPress database
- 📱 **Responsive** - Fully responsive design for all devices
- 🔍 **Conversation Management** - View, filter, and manage all conversations from admin

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

**Conversation Settings:**
- **API Key**: Enter your Gemini API key
- **Model**: Select Gemini model (Flash, Pro, etc.)
- **Company Name**: Your company name
- **AI Name**: Custom name for the AI assistant
- **AI Instructions**: Detailed instructions for AI behavior
- **Assistant Gender**: Choose masculine or feminine gender for the assistant
- **Initial Hero Message**: Message displayed on page load (use `{company_name}` for name)
- **Documentation**: Upload PDF/DOC/TXT files with service information

**Appearance Settings:**
- **Gradient Colors**: Select colors for gradient (start, end, and additional colors)
- **Font Family**: Choose font for text
- **Font Size**: Base font size in pixels
- **Animation Duration**: Control animation speed for background effects

**Video Settings:**
- **Silence Video URL**: Video to display when AI is not speaking
- **Speaking Video URL**: Video to display when AI is speaking
- **Playback Rates**: Separate playback rates for silence and speaking videos

**Voice Settings:**
- **Enable Voice**: Enable text-to-speech for AI responses
- **Voice Name**: Select voice for speech synthesis

**Lead Notification Settings:**
- **Send Lead Email**: Enable/disable email notifications when leads are captured
- **Lead Notification Email**: Email address to receive lead notifications (defaults to admin email)

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

**How it works:**
- The AI assistant naturally asks users for their contact information during conversations
- Email addresses and phone numbers are automatically extracted from conversation text
- Leads are saved to the database with conversation context
- Names are extracted and validated (must be at least 4 characters, 2+ words, start with uppercase)

**Email Notifications:**
- When a new lead is captured (new email or phone number), an email notification is sent
- Email notifications include:
  - Lead information (name, email, phone)
  - Full conversation history
  - Direct link to view the conversation in admin
- Notifications are only sent when new contact information is detected (not on every message)
- Configure notification settings in: Settings → AI Hero Assistant → Lead Notification Settings

**Viewing Leads:**
- Go to Settings → AI Hero Assistant → Conversations tab
- Filter conversations by leads (has leads / no leads)
- View full conversation history for each lead
- Export or manage leads from the admin interface

## File Structure

```
ai-hero-assistant/
├── ai-hero-assistant.php    # Main plugin file
├── includes/
│   ├── class-database.php    # Database management
│   ├── class-gemini-api.php # Gemini API integration
│   ├── class-shortcode.php  # Shortcode handler
│   ├── class-admin-settings.php # Admin settings page
│   └── class-ajax-handler.php  # AJAX request handler (includes lead extraction and email sending)
├── assets/
│   ├── css/
│   │   ├── frontend.css      # Frontend styles
│   │   ├── admin.css         # Admin styles
│   │   └── variables.css     # CSS variables
│   └── js/
│       ├── frontend.js       # Frontend JavaScript (video control, typing effect, speech synthesis)
│       ├── admin.js          # Admin JavaScript (conversation management, filtering)
│       └── markdown-formatter.js # Markdown to HTML conversion
└── README.md                 # This file
```

## Database

The plugin creates the following tables:

- `wp_aiha_conversations` - Conversations (session_id, user_ip, user_agent, conversation_json, message_count, created_at, updated_at)
- `wp_aiha_messages` - Individual messages from conversations (conversation_id, role, content, created_at)
- `wp_aiha_leads` - Captured leads (conversation_id, email, phone, name, created_at)

**Database Features:**
- Automatic table creation on plugin activation
- Schema updates handled automatically
- Message count caching for performance
- Full-text search support for message content
- Cascading deletes (deleting a conversation removes associated messages and leads)

## Requirements

- WordPress 5.0 or newer
- PHP 7.4 or newer
- Google Gemini API key
- jQuery (included in WordPress)

## Support

For issues or questions, contact the development team.

## License

GPL v2 or later

## Additional Features

### Conversation Management
- View all conversations in admin panel
- Filter conversations by:
  - IP address
  - Date range
  - Message count
  - Lead status (has leads / no leads)
  - Text search in messages
- View full conversation history in modal
- Delete single or bulk conversations
- Export conversation data

### Voice/Speech Synthesis
- Browser-based text-to-speech support
- Automatic voice selection based on settings
- Voice plays while AI is typing response
- Video switches to "speaking" state during speech
- Respects browser autoplay policies (requires user interaction)

### Lead Extraction
- Automatic detection of email addresses from conversation text
- Automatic detection of phone numbers (various formats)
- Name extraction and validation
- Lead deduplication (updates existing leads instead of creating duplicates)
- Email notifications only sent for new contact information

### Email Notifications
- Configurable email notifications on lead capture
- HTML email format with styled conversation history
- Includes lead details (name, email, phone)
- Direct admin link to view conversation
- Uses WordPress `wp_mail()` function
- Falls back to admin email if no custom email configured

## Changelog

### 1.0.0
- Initial release
- Dual video overlay system with automatic switching
- Gemini API integration
- Lead generation with automatic extraction
- Email notifications on lead capture
- Admin settings page with comprehensive options
- Conversation management interface
- Voice/speech synthesis support
- Responsive design
- Markdown formatting support
- Multilingual support (Romanian/English)
