---
name: Siwa Network - Monitoring Dashboard
colors:
    # Background & Surface (Light Mode)
    bg: "#f4f6f9"
    card: "#ffffff"
    border: "#e8edf3"
    border-dark: "#d1d9e0"
    
    # Text Colors
    text: "#2c3e50"
    text-secondary: "#6c7a89"
    text-muted: "#95a5a6"
    
    # Primary Brand
    primary: "#3498db"
    primary-hover: "#2980b9"
    
    # Status Indicators (2 colors only)
    status-normal: "#27ae60"      # Green - Device normal (tidak match rule)
    status-critical: "#e74c3c"    # Red - Device match rule (gangguan terdeteksi)
    
    # Legacy (for reference, not used in monitoring)
    success: "#27ae60"
    warning: "#e67e22"
    danger: "#e74c3c"
    info: "#2980b9"

typography:
    # Existing System Font (Segoe UI)
    font-family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
    
    # Header Sizes
    size-h2: 24px           # Page title
    size-h3: 20px           # Metric values
    size-lg: 15px           # Card customer name
    
    # Body & Label Sizes
    size-md: 14px           # Body text
    size-base: 13px         # Default base
    size-sm: 12px           # Small labels
    size-xs: 11px           # Micro labels, timestamps
    
    # Font Weights
    weight-regular: 400
    weight-medium: 500
    weight-semibold: 600
    weight-bold: 700

rounded:
    sm: 6px              # Buttons, small components
    default: 12px        # Cards, panels
    full: 9999px         # Status beacon dots

spacing:
    content-padding: 24px    # Page padding
    card-padding: 20px       # Card internal padding
    gap-grid: 20px           # Grid gap between cards
    gap-section: 24px        # Section spacing
    gap-small: 12px          # Internal element spacing
---

## Brand & Style

Design system untuk monitoring dashboard yang clean, professional, dan high-density telemetry display. Visual identity prioritas pada extreme legibility dan instant situational awareness untuk network operations monitoring.

Aesthetic: Clean light canvas dengan surgical accents untuk status indicators. Designed untuk reduce cognitive fatigue selama operational shifts. Setiap pixel serve state evaluation dan incident triage.

## Colors

Palette anchored oleh light background `#f4f6f9` dan primary blue `#3498db` untuk interactive elements dan data highlights.

### Status Palette (2 Colors Only - Simplified)

Sistem menggunakan **binary status logic** berdasarkan rule evaluation:

- **Normal (`#27ae60`)**: Pure emerald green - Device TIDAK match dengan rule Forward Chaining. Traffic dalam kondisi sehat, no alerts triggered.
- **Critical (`#e74c3c`)**: High-intensity crimson red - Device MATCH dengan rule Forward Chaining. Gangguan terdeteksi (bandwidth/latency/packet loss exceed threshold). Always paired dengan blink animation untuk immediate attention.

**Status Logic:**
```
IF device_id EXISTS in IssueLog table:
    → Status = CRITICAL (Red indicator + 4px red spine + blinking beacon)
ELSE:
    → Status = NORMAL (Green indicator + 4px green spine + solid beacon)
```

**Color Usage Rules:**
- Status colors HANYA untuk indicators (beacon dot, left spine border)
- Primary blue `#3498db` untuk IP addresses, interactive buttons
- Text colors gradient: primary `#2c3e50`, secondary `#6c7a89`, muted `#95a5a6`

### Surface System

Light mode surfaces untuk clean operational environment:
- **Base Canvas:** `#f4f6f9` - Page background
- **Cards:** `#ffffff` - Metric card panels
- **Borders:** `#e8edf3` - Hairline separation, `#d1d9e0` for hover states

## Typography

System reuse existing **Segoe UI** font family untuk consistency dengan seluruh aplikasi. Hyper-legible numerals untuk telemetry density.

### Font Stack
```css
font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
```

### Scale & Usage

- **Page Title (24px, semibold 600):** Dashboard headers, section titles
- **Customer Name (15px, semibold 600):** Card header device hostname
- **Metric Values (20px, semibold 600):** Bandwidth/Latency/Loss numbers - apply `font-variant-numeric: tabular-nums` untuk prevent layout shift
- **Body Text (14px, regular 400):** Descriptions, subtitles
- **Base (13px, regular 400):** Default text
- **Labels (12px, medium 500):** Metric parameter labels (BANDWIDTH, LATENCY, LOSS) - uppercase with letter-spacing
- **Small (11px, medium 500):** Timestamps, micro-copy, "Update terakhir" info
- **IP Address (13px, regular 400):** Monospace (`Courier New`) dalam primary blue color

**Typography Rules:**
- Metric values MUST use `tabular-nums` untuk prevent width jump saat angka berubah
- Labels UPPERCASE dengan letter-spacing 0.5px untuk visual hierarchy
- IP addresses always monospace font untuk technical data clarity

## Layout & Spacing

Flexible high-density grid untuk support multiple device cards.

### Grid Architecture

- **Desktop (> 1440px):** Auto-fill grid dengan minimum 320px per card, 20px gap
- **Laptop/Tablet (768px - 1439px):** 2-3 column auto-flow
- **Mobile (< 768px):** 1-column stack, 16px horizontal margins

**Grid CSS:**
```css
display: grid;
grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
gap: 20px;
```

### Component Spacing

- **Page Padding:** 24px dari viewport edge
- **Card Internal Padding:** 20px untuk optimal density tanpa collision
- **Section Gap:** 24px vertical spacing antara header dan grid
- **Metric Row Gap:** 12px antara bandwidth/latency/loss rows
- **Micro Gaps:** 4px antara metric value dan unit suffix (e.g., `85.5` | `Mbps`)

## Elevation & Depth

Low-contrast hairline boundaries dengan subtle hover lift. No heavy drop shadows.

### Elevation Hierarchy

- **Level 0 (Canvas Base):** Flat `#f4f6f9` background
- **Level 1 (Metric Cards):** White `#ffffff` card dengan 1px solid `#e8edf3` border, radius 12px
- **Level 2 (Hover State):** `transform: translateY(-2px)` + `box-shadow: 0 4px 16px rgba(44, 62, 80, 0.12)`
- **Critical Alert Emphasis:** Red left spine 4px + blinking beacon dot (no additional elevation)

**No Glows, No Gradients:** Clean flat design dengan functional borders only.

## Shapes

Controlled geometric curvature untuk professional aesthetic.

- **Card Radius (12px):** All metric cards, panels
- **Button Radius (6px):** Primary action buttons, small components
- **Status Beacon (50% / full circle):** 10px × 10px circular status dots

Avoid overly playful bubble aesthetics - maintain technical precision dengan soft edges.

## Components

### Metric Cards

**Structure:**
- Surface: `#ffffff` white card
- Border: 1px solid `#e8edf3`
- Border Radius: 12px
- Padding: 20px internal
- **Status Spine:** 4px left border accent (green normal / red critical)

**Sections:**
1. **Header:** Customer name (15px semibold) + Status beacon (10px circle)
2. **Device Info:** IP address (monospace, primary blue) + Device model (12px secondary text)
3. **Metrics Grid:** 3 rows (Bandwidth, Latency, Loss) - label left, value right
4. **Footer:** Timestamp (11px muted) - "2 menit yang lalu"

**Dividers:** 1px `#e8edf3` hairline untuk section separation (device info bottom, footer top)

### Status Indicators

**Beacon Dot (10px circle):**
- Normal: Solid green `#27ae60`, no animation
- Critical: Solid red `#e74c3c` + blink animation (`opacity 1 → 0.3 → 1, 1s infinite`)

**Left Spine (4px width):**
- Normal: `border-left: 4px solid #27ae60`
- Critical: `border-left: 4px solid #e74c3c`

**No other visual indicators** - binary status only (red/green).

### Buttons

**Primary Action Button:**
- Background: `#3498db` blue
- Text: White, 12px medium weight
- Radius: 6px
- Padding: 10px horizontal, 20px vertical
- Hover: `#2980b9` darker blue + subtle shadow

**Style:**
```css
.btn-primary {
    background: #3498db;
    color: white;
    border-radius: 6px;
    padding: 10px 20px;
    font-size: 12px;
    font-weight: 500;
}
.btn-primary:hover {
    background: #2980b9;
    box-shadow: 0 2px 8px rgba(44, 62, 80, 0.08);
}
```

### Monospace Data (IP Addresses)

IP addresses display dalam monospace font (`Courier New`) colored primary blue `#3498db`:

```css
.ip-address {
    font-family: 'Courier New', monospace;
    color: #3498db;
    font-size: 13px;
}
```

**Technical Precision:** Monospace untuk fixed-width numerical data clarity.

### Metric Display Format

**Label (Uppercase, 11px medium):**
```
BANDWIDTH    85.5 Mbps
LATENCY      42.3 ms
LOSS         1.2 %
```

**Value (20px semibold, tabular-nums):**
```css
.metric-value {
    font-size: 20px;
    font-weight: 600;
    font-variant-numeric: tabular-nums; /* Prevent layout shift */
}
```

**Unit (12px regular, secondary color):**
- Separated 4px gap dari value
- Color: `#6c7a89` (text-secondary)

## Animation Rules

**Critical State Blink (Status Beacon Only):**
```css
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
/* Applied only to .status-critical .status-beacon */
animation: blink 1s ease-in-out infinite;
```

**Loading Pulse (Connection Indicator):**
```css
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}
/* Applied to .pulse-dot during data fetch */
animation: pulse 1.5s ease-in-out infinite;
```

**Hover Lift (Card Elevation):**
```css
.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(44, 62, 80, 0.12);
    transition: all 0.2s ease;
}
```

**No other animations** - keep interface responsive dan stable untuk operational monitoring.

## Responsive Behavior

**Desktop (> 1440px):**
- Grid: Auto-fill columns, minimum 320px per card
- Max width: 1400px container centered
- Full header horizontal layout

**Tablet/Laptop (768px - 1439px):**
- Grid: 2-3 columns auto-flow
- Maintain card padding 20px
- Header may wrap title dan info sections

**Mobile (< 768px):**
- Grid: 1 column stack
- Page padding: 16px (reduce from 24px)
- Header: Vertical stack, left-align
- Card padding: Keep 20px (density critical even on mobile)

**Breakpoint:**
```css
@media (max-width: 768px) {
    .metrics-grid { grid-template-columns: 1fr; }
    .dashboard-container { padding: 16px; }
    .dashboard-header { flex-direction: column; }
}
```

## Implementation Notes

**Consistency Rules:**
1. Reuse existing CSS variables dari `app.css` (no custom tokens)
2. Font: Segoe UI stack (no external fonts)
3. Status: Binary logic only (green normal, red critical)
4. No warning/amber states (removed for simplicity)
5. Tabular-nums mandatory untuk metric values
6. Monospace mandatory untuk IP addresses

**Performance:**
- Grid layout native CSS (no JS framework overhead)
- Auto-refresh 60s interval (sync CRON cycle)
- Lazy load metrics via API endpoint (no inline data)

**Accessibility:**
- Color contrast ratio meet WCAG AA (green `#27ae60`, red `#e74c3c` pada white background)
- Focus states untuk keyboard navigation
- ARIA labels untuk status indicators
