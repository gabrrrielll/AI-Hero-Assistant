# AI Hero Assistant - WordPress Plugin

Un plugin WordPress avansat care adaugă un chatbot AI cu chip animat în hero section, integrat cu Google Gemini API.

## Caracteristici

- 🤖 **Chip animat abstract** - Sistem de particule care formează un chip umanoid abstract cu gură vorbitoare
- 💬 **Chatbot AI** - Integrare completă cu Google Gemini API
- 🌍 **Multilingv** - Detectare automată a limbii și răspunsuri în limba utilizatorului
- 📝 **Typing Effect** - Subtitrare animată cu efect de scriere caracter cu caracter
- 🎨 **Personalizabil** - Gradient colors, fonturi și mesaje configurabile din admin
- 📊 **Lead Generation** - Capturare automată de email/telefon din conversații
- 💾 **Database** - Salvare conversații și leads în baza de date WordPress
- 📱 **Responsive** - Design complet responsive pentru toate dispozitivele

## Instalare

1. Copiază folderul `ai-hero-assistant` în directorul `wp-content/plugins/` al site-ului WordPress
2. Activează plugin-ul din panoul de administrare WordPress (Plugins → Installed Plugins)
3. Mergi la Settings → AI Hero Assistant pentru configurare

## Configurare

### 1. Obține API Key Gemini

1. Accesează [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Creează un cont sau conectează-te
3. Generează o cheie API nouă
4. Copiază cheia în câmpul "Google Gemini API Key" din setările plugin-ului

### 2. Configurează Setările

În pagina de setări (`Settings → AI Hero Assistant`):

- **API Key**: Introdu cheia API Gemini
- **Model**: Selectează modelul Gemini (Flash, Pro, etc.)
- **Nume Firmă**: Numele firmei tale
- **Mesaj Inițial Hero**: Mesajul afișat la încărcarea paginii (folosește `{company_name}` pentru nume)
- **Instrucțiuni AI**: Instrucțiuni detaliate pentru comportamentul AI
- **Documentație**: Încarcă fișiere PDF/DOC/TXT cu informații despre servicii
- **Culori Gradient**: Selectează culorile pentru gradient
- **Font Family**: Alege fontul pentru text

### 3. Adaugă Shortcode în Pagină

Adaugă shortcode-ul `[ai_hero_assistant]` în pagina de home sau în hero section:

```php
[ai_hero_assistant height="600px"]
```

Sau în editorul de pagini:
- Adaugă un bloc "Shortcode"
- Introdu: `[ai_hero_assistant]`

## Utilizare

### Shortcode

```
[ai_hero_assistant]
```

Parametri opționali:
- `height` - Înălțimea secțiunii hero (ex: "600px", "80vh")

### Lead Generation

Plugin-ul detectează automat email-uri și numere de telefon din conversațiile utilizatorilor și le salvează în baza de date. Poți vedea leads-urile capturate în pagina de setări.

## Structură Fișiere

```
ai-hero-assistant/
├── ai-hero-assistant.php    # Fișier principal plugin
├── includes/
│   ├── class-database.php    # Gestionare baza de date
│   ├── class-gemini-api.php # Integrare Gemini API
│   ├── class-shortcode.php  # Shortcode handler
│   ├── class-admin-settings.php # Pagină setări admin
│   └── class-ajax-handler.php  # Handler AJAX requests
├── assets/
│   ├── css/
│   │   ├── frontend.css      # Stiluri frontend
│   │   └── admin.css         # Stiluri admin
│   └── js/
│       ├── frontend.js       # JavaScript frontend (particule, typing effect)
│       └── admin.js          # JavaScript admin
└── README.md                 # Acest fișier
```

## Baza de Date

Plugin-ul creează următoarele tabele:

- `wp_aiha_conversations` - Conversații (session_id, user_ip, etc.)
- `wp_aiha_messages` - Mesaje individuale din conversații
- `wp_aiha_leads` - Leads capturate (email, telefon, nume)

## Cerințe

- WordPress 5.0 sau mai nou
- PHP 7.4 sau mai nou
- Cheie API Google Gemini
- jQuery (inclus în WordPress)

## Suport

Pentru probleme sau întrebări, contactează echipa de dezvoltare.

## Licență

GPL v2 or later

## Changelog

### 1.0.0
- Lansare inițială
- Chip animat cu particule
- Integrare Gemini API
- Lead generation
- Admin settings page
- Responsive design


