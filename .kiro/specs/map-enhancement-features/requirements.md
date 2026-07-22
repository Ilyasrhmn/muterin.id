# Requirements Document

## Introduction

Sistem peta saat ini memiliki beberapa keterbatasan UX dibandingkan dengan Google Maps. User tidak bisa dengan mudah menggunakan lokasi mereka saat ini, marker/pin masih berupa dot sederhana yang kurang interaktif, dan tidak ada konteks cerita untuk setiap titik yang ditambahkan. Requirements ini bertujuan untuk meningkatkan user experience dengan menambahkan fitur "Use Current Location", enhanced pin icons yang lebih visual, dan kemampuan menambahkan cerita/konteks pada setiap titik.

## Glossary

- **Current_Location**: Lokasi geografis user saat ini yang didapat dari browser geolocation API
- **Pin**: Marker atau titik pada peta yang menandai lokasi tertentu
- **Pin_Icon**: Icon visual yang digunakan untuk menampilkan pin pada peta
- **Story**: Cerita atau catatan kontekstual yang ditambahkan user pada sebuah pin
- **Route_Plan**: Rencana rute perjalanan dari titik awal ke titik tujuan
- **Waypoint**: Titik perhentian dalam sebuah rute

## Requirements

### Requirement 1: Current Location Button for Pins

**User Story:** Sebagai user, saya ingin menggunakan lokasi saya saat ini ketika menambahkan titik baru, sehingga saya tidak perlu mencari lokasi saya secara manual di peta.

#### Acceptance Criteria

1. WHEN adding a new pin, THE System SHALL display a "Gunakan Lokasi Saya" button
2. WHEN the "Gunakan Lokasi Saya" button is clicked, THE System SHALL request geolocation permission from browser
3. WHEN geolocation permission is granted, THE System SHALL retrieve user's current coordinates
4. WHEN current location is retrieved, THE System SHALL automatically place the pin at user's current location
5. WHEN geolocation permission is denied, THE System SHALL display an error message explaining the need for permission
6. WHEN geolocation is not supported by browser, THE System SHALL hide the "Gunakan Lokasi Saya" button

### Requirement 2: Current Location for Route Planning

**User Story:** Sebagai user yang merencanakan rute, saya ingin menggunakan lokasi saya saat ini sebagai titik awal atau tujuan, sehingga saya bisa merencanakan perjalanan dari posisi saya sekarang.

#### Acceptance Criteria

1. WHEN setting a start point for route, THE System SHALL display a "Gunakan Lokasi Saya" button
2. WHEN setting an end point for route, THE System SHALL display a "Gunakan Lokasi Saya" button
3. WHEN the button is clicked for start point, THE System SHALL set user's current location as the route start
4. WHEN the button is clicked for end point, THE System SHALL set user's current location as the route destination
5. WHEN current location is set, THE System SHALL automatically trigger route calculation
6. THE System SHALL display a location marker icon on the button for visual clarity

### Requirement 3: Enhanced Pin Icons

**User Story:** Sebagai user, saya ingin melihat pin dengan icon yang jelas dan menarik seperti Google Maps, sehingga peta lebih mudah dibaca dan lebih profesional.

#### Acceptance Criteria

1. THE System SHALL use custom icon markers instead of simple colored dots
2. WHEN a pin category is "infrastruktur", THE System SHALL display an appropriate infrastructure icon
3. WHEN a pin category is "bencana", THE System SHALL display an appropriate disaster icon
4. WHEN a pin category is "layanan", THE System SHALL display an appropriate service icon
5. WHEN a pin category is "lainnya", THE System SHALL display a generic location icon
6. THE Pin_Icon SHALL have a clear visual design with contrasting colors
7. THE Pin_Icon SHALL scale appropriately with map zoom level
8. WHEN hovering over a pin, THE System SHALL show a slight scale animation for interactivity

### Requirement 4: Story/Context for Pins

**User Story:** Sebagai user, saya ingin menambahkan cerita atau konteks saat membuat pin, sehingga saya bisa mengingat kenapa titik tersebut penting dan berbagi informasi dengan orang lain.

#### Acceptance Criteria

1. WHEN creating a new pin, THE System SHALL provide a "Cerita/Catatan" optional text field
2. THE text field SHALL support multi-line input up to 500 characters
3. WHEN a pin has a story, THE System SHALL display a story icon badge on the pin marker
4. WHEN clicking a pin with a story, THE System SHALL display the story in the popup
5. WHEN editing a pin, THE System SHALL allow updating the story content
6. WHEN deleting a pin, THE System SHALL also delete the associated story

### Requirement 5: Pin Icon Customization System

**User Story:** Sebagai developer, saya ingin sistem icon yang mudah dikustomisasi, sehingga icon baru bisa ditambahkan tanpa mengubah banyak kode.

#### Acceptance Criteria

1. THE System SHALL use a centralized icon mapping configuration
2. WHEN a new category is added, THE System SHALL support adding new icon mappings
3. THE Icon configuration SHALL include icon type, color, and size properties
4. THE System SHALL use Font Awesome or similar icon library for scalability
5. THE System SHALL fallback to a default icon if category icon is not defined

### Requirement 6: Geolocation Error Handling

**User Story:** Sebagai user, saya ingin mendapat feedback yang jelas ketika geolocation gagal, sehingga saya tahu apa yang harus dilakukan.

#### Acceptance Criteria

1. WHEN geolocation times out, THE System SHALL display "Lokasi tidak ditemukan, coba lagi"
2. WHEN permission is denied, THE System SHALL display "Izin lokasi ditolak. Aktifkan di pengaturan browser"
3. WHEN position is unavailable, THE System SHALL display "Posisi tidak tersedia saat ini"
4. WHEN geolocation errors occur, THE System SHALL keep the manual pin placement option available
5. THE error messages SHALL be displayed using the dialog component for consistency

### Requirement 7: Pin Popup Enhancement

**User Story:** Sebagai user, saya ingin popup pin menampilkan semua informasi penting dengan layout yang rapi, sehingga mudah dibaca dan dipahami.

#### Acceptance Criteria

1. WHEN a pin popup is displayed, THE System SHALL show title, category, and date prominently
2. WHEN a pin has a story, THE popup SHALL display the story in a dedicated section
3. THE popup SHALL display coordinates in a subtle, formatted manner
4. THE popup SHALL include edit and delete action buttons
5. THE popup layout SHALL be responsive and work well on mobile devices
6. WHEN popup content exceeds viewport, THE System SHALL provide scrolling

### Requirement 8: Loading States for Current Location

**User Story:** Sebagai user, saya ingin melihat feedback visual ketika sistem mengambil lokasi saya, sehingga saya tahu sistem sedang bekerja.

#### Acceptance Criteria

1. WHEN "Gunakan Lokasi Saya" is clicked, THE button SHALL show a loading spinner
2. WHEN loading, THE button text SHALL change to "Mengambil lokasi..."
3. WHEN loading, THE button SHALL be disabled to prevent multiple clicks
4. WHEN location is retrieved successfully, THE System SHALL show a brief success indicator
5. WHEN error occurs, THE loading state SHALL be cleared and button reset

