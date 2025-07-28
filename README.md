# 🎵 Professional Beatbox Machine

A powerful, web-based beat machine and sound design studio with AI-powered sound effects generation. Create professional beats, manage sound libraries, and generate custom sound effects using ElevenLabs AI technology.

![Beatbox Machine](https://img.shields.io/badge/PHP-7.4+-blue) ![SQLite](https://img.shields.io/badge/Database-SQLite-green) ![ElevenLabs](https://img.shields.io/badge/AI-ElevenLabs-purple) ![Web Audio API](https://img.shields.io/badge/Audio-Web%20Audio%20API-orange)

## ✨ Features

### 🎛️ **Professional Beat Sequencer**
- **16-step sequencer** with expandable tracks (starts with 8)
- **Real-time playback** with visual step indicators
- **Individual track controls** (volume, mute, clear, delete)
- **Pattern management** (create, switch, delete multiple patterns)
- **Professional transport controls** (play, pause, stop, rewind)

### 🎵 **Advanced Sound Engine**
- **Web Audio API synthesis** with ADSR envelopes
- **Multiple waveforms** (sine, square, triangle, sawtooth)
- **Built-in sound presets** (kicks, snares, hi-hats, bass, synths)
- **Real-time audio processing** with filters and effects
- **Swing/groove control** for humanized timing

### 🤖 **AI Sound Effects Generation**
- **ElevenLabs integration** for text-to-sound generation
- **Advanced control options** (duration, prompt influence)
- **Category organization** for generated sounds
- **Instant integration** with the sequencer
- **Professional sound design** capabilities

### 📁 **Sound Library Management**
- **Organized categories** (Drums, Melodic, FX, Custom, Generated)
- **File upload support** for custom audio samples
- **Drag-and-drop workflow** for sound selection
- **Sound preview** and deletion capabilities
- **Custom category creation**

### 💾 **Data Persistence**
- **SQLite database** for reliable storage
- **Pattern save/load** functionality
- **Settings persistence** (API keys, preferences)
- **Sound library management** with metadata
- **Export capabilities** (JSON format)

### 🎨 **Professional UI/UX**
- **Black beatbox machine aesthetic** with gradients and glows
- **Responsive design** with proper scrolling
- **Intuitive workflow** with visual feedback
- **Professional controls** mimicking hardware interfaces
- **Toggle sidebar** for optimal workspace management

## 🚀 Quick Start

### Prerequisites
- **PHP 7.4+** with SQLite support
- **Web server** (Apache, Nginx, or PHP built-in server)
- **Modern web browser** with Web Audio API support
- **ElevenLabs API key** (optional, for AI sound generation)

### Installation

1. **Clone or download** the project:
```bash
git clone <repository-url>
cd beatbox-machine
```

2. **Start the server**:
```bash
# Using PHP built-in server
php -S localhost:8000

# Or place index.php in your web server directory
```

3. **Access the application**:
```
http://localhost:8000
```

4. **Database auto-creation**: The SQLite database will be created automatically on first run.

## ⚙️ ElevenLabs API Setup

### Get Your API Key
1. Visit [ElevenLabs Dashboard](https://elevenlabs.io/app/settings/api-keys)
2. Sign up/login to your account
3. Navigate to **Settings > API Keys**
4. **Create a new API key** or copy an existing one

### Configure in Beatbox Machine
1. **Click the ⚙️ gear icon** in the header
2. **Paste your API key** in the input field
3. **Click 💾 to save** - you'll see a ✅ confirmation
4. **Start generating sounds** in the Sound Library sidebar

### API Usage & Pricing
- ElevenLabs offers a **free tier** with limited generations
- Check [ElevenLabs Pricing](https://elevenlabs.io/pricing) for current rates
- Sound generation uses the `/v1/sound-generation` endpoint

## 🎵 How to Use

### Basic Beat Making
1. **Select a sound** from the Sound Library (click to expand sidebar)
2. **Click grid squares** to place/remove sounds on tracks
3. **Press Play ▶️** to start the sequence
4. **Adjust tempo, volume, and swing** with the sliders

### Track Management
- **Add tracks** with the "+ Add Track" button
- **Mute tracks** with the "M" button
- **Adjust individual track volume** with sliders
- **Clear tracks** with the "Clear" button
- **Delete tracks** with the "Del" button

### Pattern Management
- **Switch between patterns** using the pattern tabs
- **Create new patterns** with the "+ Add Pattern" button
- **Delete patterns** with the × button (must have multiple patterns)

### AI Sound Generation
1. **Expand the Sound Library** sidebar
2. **Describe your desired sound** in the AI section:
   - "Epic drum hit with deep reverb"
   - "Futuristic laser beam sound"
   - "Atmospheric pad with ethereal reverb"
3. **Adjust advanced options** (duration, prompt influence, category)
4. **Click "Generate Sound Effect"**
5. **Generated sound auto-appears** in the library and can be used immediately

### File Management
- **Upload custom audio** files using the upload buttons
- **Save patterns** to database with the Save button
- **Export patterns** as JSON files for backup/sharing
- **Record audio** from microphone (basic implementation)

## 🛠️ Technical Details

### Technology Stack
- **Frontend**: Vanilla JavaScript, CSS3, HTML5
- **Backend**: PHP 7.4+ with PDO
- **Database**: SQLite 3
- **Audio**: Web Audio API
- **AI Integration**: ElevenLabs Sound Effects API
- **File Handling**: PHP file upload with security validation

### File Structure
```
beatbox-machine/
├── index.php              # Main application file (all-in-one)
├── beatbox.db             # SQLite database (auto-created)
├── uploads/               # User-uploaded audio files (auto-created)
└── README.md              # This file
```

### Database Schema
```sql
-- Patterns table
patterns (id, name, data, created_at)

-- Sounds table  
sounds (id, name, category, subcategory, file_path, sound_data, created_at)

-- Tracks table
tracks (id, pattern_id, name, volume, muted, color, created_at)

-- Settings table
settings (id, setting_key, setting_value, created_at)
```

### Audio Processing
- **Real-time synthesis** using Web Audio API oscillators
- **ADSR envelope shaping** for professional sound design
- **Low-pass filtering** with adjustable cutoff and resonance
- **Multi-track mixing** with individual volume controls
- **Swing timing** implementation for groove

## 🎨 Customization

### Adding New Sounds
1. **Built-in sounds**: Edit the `samplePresets` object in JavaScript
2. **Upload files**: Use the upload functionality in the UI
3. **AI generation**: Use the ElevenLabs integration
4. **Custom synthesis**: Modify the `createAdvancedSound` function

### Styling
- All styles are contained in the `<style>` section of `index.php`
- **CSS variables** can be added for easier theme customization
- **Gradient colors** and **glow effects** define the aesthetic

### Database Extensions
- Add new tables by modifying the PHP database initialization
- **Settings table** can store additional user preferences
- **Tracks table** can be extended with more properties

## 🐛 Troubleshooting

### Common Issues

**Database not created**
- Ensure PHP has SQLite extension enabled
- Check file permissions in the directory
- Verify web server has write access

**Sounds not playing**
- Check browser console for Web Audio API errors
- Ensure browser supports Web Audio API (Chrome, Firefox, Safari)
- Try clicking play button to initialize audio context (required by some browsers)

**ElevenLabs generation fails**
- Verify API key is correct and saved
- Check your ElevenLabs account limits/credits
- Ensure internet connection is stable
- Check browser console for detailed error messages

**File upload issues**
- Check PHP `upload_max_filesize` and `post_max_size` settings
- Ensure `uploads/` directory is writable
- Verify file is a supported audio format

### Browser Support
- **Chrome 66+** (recommended)
- **Firefox 60+**
- **Safari 14+**
- **Edge 79+**

### Performance Tips
- **Limit concurrent sounds** for better performance
- **Use shorter audio files** for faster loading
- **Clear unused patterns** to reduce memory usage
- **Close other audio applications** to avoid conflicts

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Make your changes in `index.php`
3. Test thoroughly with different browsers
4. Submit a pull request with detailed description

### Feature Requests
- **Audio effects** (reverb, delay, distortion)
- **MIDI support** for external controllers
- **Song arrangement** mode for full track creation
- **Collaboration features** for sharing patterns
- **Mobile responsive** optimizations

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🙏 Acknowledgments

- **ElevenLabs** for the amazing Sound Effects API
- **Web Audio API** developers for enabling browser-based audio
- **PHP and SQLite** communities for robust backend tools
- **CSS Gradient** techniques for the beautiful UI design

## 📞 Support

- **Issues**: Create an issue in the repository
- **Questions**: Check the troubleshooting section first
- **Feature Requests**: Open a discussion or issue

---

**Made with ❤️ for music producers and beat makers**

🎵 *Start creating professional beats today!* 🎵