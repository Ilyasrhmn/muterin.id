# Implementation Plan: Map Enhancement Features

## Overview

Implementasi tiga fitur utama untuk meningkatkan UX peta: (1) Current Location buttons, (2) Enhanced pin icons dengan Font Awesome, dan (3) Story/context field untuk pins. Fokus pada visual improvement dan user convenience tanpa breaking changes pada API yang ada.

## Tasks

- [ ] 1. Create geolocation utility module
  - Create `public/js/geolocation.js` file
  - Implement `isSupported()` function to check browser support
  - Implement `getCurrentPosition()` with Promise-based API
  - Implement `getErrorMessage()` for user-friendly error messages
  - Handle all geolocation error codes (PERMISSION_DENIED, POSITION_UNAVAILABLE, TIMEOUT)
  - Add timeout configuration (10 seconds default)
  - Export as `window.MuterinGeolocation` module
  - _Requirements: 1.2, 1.3, 1.5, 1.6, 6.1, 6.2, 6.3, 6.4_

- [ ] 2. Add Font Awesome to project
  - Add Font Awesome 6 CDN link to main layout file
  - Verify icon rendering works in browser
  - Add fallback if CDN fails
  - _Requirements: 3.1, 5.4_

- [ ] 3. Create pin icon configuration system
  - Create icon mapping object `window.MuterinPinIcons`
  - Define icon, color, and bgColor for each category (infrastruktur, bencana, layanan, lainnya)
  - Implement `getIconConfig(category)` function with fallback to 'lainnya'
  - Use Font Awesome icon names (fa-road-barrier, fa-triangle-exclamation, fa-building, fa-location-dot)
  - _Requirements: 3.2, 3.3, 3.4, 3.5, 5.1, 5.2, 5.3, 5.5_

- [ ] 4. Implement custom pin marker rendering
  - [ ] 4.1 Create `createPinMarker()` function
    - Use L.divIcon for custom HTML markers
    - Render Font Awesome icon in colored circle
    - Add shadow and hover scale animation
    - Add story badge indicator when pin has note
    - Set proper iconSize, iconAnchor, and popupAnchor
    - _Requirements: 3.1, 3.6, 3.7, 3.8, 4.3_
  
  - [ ] 4.2 Update map-pins.js to use custom markers
    - Replace L.circleMarker with createPinMarker()
    - Pass category and hasStory parameters
    - Maintain existing popup binding
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 5. Enhance pin popup display
  - Create `createPinPopup()` function
  - Display category icon and label in header
  - Add dedicated story section with amber background
  - Show story icon and label
  - Display formatted date
  - Add edit and delete action buttons
  - Make popup responsive and scrollable
  - _Requirements: 4.4, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

- [ ] 6. Add current location FAB for pins
  - [ ] 6.1 Create floating action button (FAB)
    - Position fixed at bottom-right
    - Use location-crosshairs icon
    - Add shadow and hover animations
    - Only show if geolocation is supported
    - _Requirements: 1.1, 1.6_
  
  - [ ] 6.2 Implement FAB click handler
    - Show loading spinner on button
    - Call getCurrentPosition()
    - Pan map to current location with flyTo animation
    - Trigger pin creation prompt at current location
    - Handle errors and show dialog
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 7. Enhance pin creation dialog
  - Update prompt dialog for pin creation
  - Change "Catatan" label to "Cerita/Catatan"
  - Update placeholder to "Ceritakan apa yang terjadi di sini..."
  - Increase textarea rows to 3 for better UX
  - Update dialog title when using current location
  - _Requirements: 4.1, 4.2_

- [ ] 8. Add current location buttons to route planning
  - [ ] 8.1 Create `createLocationButton()` component
    - Inline flex layout with icon and text
    - Loading spinner element (hidden by default)
    - Proper Tailwind classes for styling
    - Disabled state handling
    - _Requirements: 2.1, 2.2, 2.6, 8.1, 8.2, 8.3_
  
  - [ ] 8.2 Add buttons to route start/end containers
    - Find start point container in DOM
    - Find end point container in DOM
    - Append location buttons if geolocation supported
    - Wire up click handlers
    - _Requirements: 2.1, 2.2_
  
  - [ ] 8.3 Implement route location click handler
    - Set button to loading state
    - Get current position
    - Reverse geocode to get location label
    - Set as start or end point based on button context
    - Pan map to location
    - Trigger route calculation automatically
    - Handle errors gracefully
    - _Requirements: 2.3, 2.4, 2.5, 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 9. Update map initialization
  - Load geolocation.js in layout
  - Load pin icon configuration
  - Initialize current location FAB for pins page
  - Initialize location buttons for route planning page
  - _Requirements: ALL_

- [ ] 10. Add CSS for custom markers
  - Create styles for .custom-pin-marker
  - Add transition animations
  - Ensure proper z-index layering
  - Add hover effects
  - _Requirements: 3.7, 3.8_

- [ ] 11. Checkpoint - Test all map features
  - Test current location FAB on pins page
  - Test pin creation with story field
  - Test custom pin icons render for all categories
  - Test story badge appears on pins with notes
  - Test enhanced popups show story section
  - Test current location buttons on route planning
  - Test setting current location as start point
  - Test setting current location as end point
  - Test route calculation triggers automatically
  - Test all geolocation error scenarios
  - Test on mobile devices with real GPS
  - Ensure all tests pass, ask the user if questions arise
  - _Requirements: ALL_

- [ ]* 12. Write unit tests for geolocation module
  - Test isSupported() returns correct boolean
  - Test getCurrentPosition() resolves with lat/lng
  - Test getErrorMessage() for each error code
  - Test timeout configuration
  - Test promise rejection on errors
  - _Requirements: 1.2, 1.3, 1.5, 6.1, 6.2, 6.3_

- [ ]* 13. Write integration tests
  - Test complete pin creation flow with current location
  - Test route planning with current location
  - Test pin icon rendering for all categories
  - Test story persistence after reload
  - Test popup display with and without story
  - Test error handling when permission denied
  - _Requirements: ALL_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster implementation
- Font Awesome 6 Free is used for icons (no license needed for free icons)
- Maintain backward compatibility with existing pins (they will use default icon)
- Story field uses existing `note` column in database - no migration needed
- Focus on mobile-friendly design for GPS features
- All geolocation features degrade gracefully when not supported

