# Implementation Plan: Dialog UX Improvement

## Overview

Implementasi perbaikan UX untuk dialog component dengan menambahkan backdrop blur, smooth animations, focus trap, dan body scroll lock. Fokus pada perubahan CSS dan JavaScript enhancement tanpa merubah struktur data atau API.

## Tasks

- [ ] 1. Update dialog HTML markup with enhanced styling
  - Modify `resources/views/components/ui/dialog.blade.php`
  - Add backdrop-blur classes to backdrop element
  - Update z-index to z-[9999] for root dialog element
  - Add opacity and transform classes for animation states
  - Add transition classes to all animated elements
  - Update button classes with active:scale-95 feedback
  - Add proper ARIA attributes for accessibility
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.5, 3.1, 3.2, 3.3_

- [ ] 2. Implement body scroll lock functionality
  - [ ] 2.1 Add lockBodyScroll() function to dialog.js
    - Set body overflow to hidden
    - Calculate and compensate scrollbar width
    - Apply padding-right to prevent layout shift
    - _Requirements: 4.1, 5.5_
  
  - [ ] 2.2 Add unlockBodyScroll() function to dialog.js
    - Restore body overflow
    - Remove padding-right compensation
    - _Requirements: 4.2_
  
  - [ ] 2.3 Add getScrollbarWidth() helper function
    - Calculate scrollbar width using window.innerWidth - documentElement.clientWidth
    - _Requirements: 5.5_

- [ ] 3. Implement focus trap mechanism
  - [ ] 3.1 Add updateFocusableElements() function
    - Query all focusable elements within dialog
    - Filter out disabled and hidden elements
    - Store first and last focusable elements
    - _Requirements: 4.3_
  
  - [ ] 3.2 Add trapFocus() keyboard event handler
    - Handle Tab key to cycle forward
    - Handle Shift+Tab to cycle backward
    - Prevent focus from leaving dialog
    - _Requirements: 4.4_
  
  - [ ] 3.3 Integrate focus trap into open/close lifecycle
    - Call updateFocusableElements() when dialog opens
    - Add trapFocus listener on open
    - Remove trapFocus listener on close
    - Auto-focus first element after animation
    - _Requirements: 4.3, 4.4_

- [ ] 4. Enhance dialog animation system
  - [ ] 4.1 Update open() function with animation sequencing
    - Remove 'hidden' class
    - Trigger reflow to enable transitions
    - Remove 'opacity-0' class for fade in
    - Add 'scale-100' and remove 'scale-95' for scale up
    - Call lockBodyScroll()
    - Call updateFocusableElements() and setup focus trap
    - Auto-focus appropriate element after delay
    - _Requirements: 3.1, 3.2, 3.4, 4.1, 4.3_
  
  - [ ] 4.2 Update close() function with animation sequencing
    - Add 'opacity-0' class for fade out
    - Add 'scale-95' and remove 'scale-100' for scale down
    - Remove focus trap listener
    - Use setTimeout to add 'hidden' class after animation completes (300ms)
    - Call unlockBodyScroll() after animation
    - _Requirements: 3.3, 3.4, 4.2_

- [ ] 5. Update button class constants with new styles
  - Update BTN_PRIMARY constant with transition-all and active:scale-95
  - Update BTN_DANGER constant with transition-all and active:scale-95
  - Ensure duration-200 is used for button transitions
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 6. Verify Tailwind configuration for backdrop-blur
  - Check if backdrop-blur utility is available in tailwind.config.js
  - Add backdrop-blur configuration if not present
  - Ensure -webkit-backdrop-filter is generated for Safari support
  - _Requirements: 1.3, 5.1, 5.2_

- [ ] 7. Checkpoint - Test all dialog interactions
  - Open various dialog types (confirm, prompt, alert)
  - Verify backdrop blur is visible and smooth
  - Test keyboard navigation with Tab/Shift+Tab
  - Verify focus trap keeps focus within dialog
  - Test body scroll lock prevents background scrolling
  - Verify no layout shift when dialog opens
  - Test Escape and Enter key handlers still work
  - Test in Chrome, Firefox, Safari, and Edge
  - Ensure all tests pass, ask the user if questions arise.
  - _Requirements: ALL_

- [ ]* 8. Write unit tests for dialog functionality
  - Test lockBodyScroll() sets correct styles
  - Test unlockBodyScroll() removes styles
  - Test getScrollbarWidth() calculation
  - Test updateFocusableElements() filters correctly
  - Test open() and close() animation sequencing
  - _Requirements: 4.1, 4.2, 5.5_

- [ ]* 9. Write integration tests for complete flows
  - Test complete user flow: open → interact → close
  - Test keyboard navigation through all focusable elements
  - Test backdrop click closes dialog
  - Test multiple sequential dialog opens
  - Test all dialog modes (confirm, prompt, alert)
  - _Requirements: ALL_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster implementation
- Focus on visual and UX improvements first, then accessibility features
- Maintain backward compatibility with existing AmictaDialog API
- No database migrations or model changes required
- All changes are in presentation layer (Blade template and JavaScript)
