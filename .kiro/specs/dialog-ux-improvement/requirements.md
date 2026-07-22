# Requirements Document

## Introduction

Sistem dialog/popup saat ini memiliki masalah UX di mana backdrop tidak memiliki blur effect yang proper, menyebabkan konten di belakang popup masih terlihat jelas dan mengganggu fokus user. Requirement ini bertujuan untuk memperbaiki bug tersebut dan meningkatkan user experience dengan menambahkan backdrop blur yang membuat user fokus pada dialog.

## Glossary

- **Dialog**: Komponen popup modal yang digunakan untuk prompt, confirm, dan alert
- **Backdrop**: Layer overlay di belakang dialog yang menutupi konten halaman
- **Backdrop_Blur**: Efek visual yang membuat konten di belakang dialog menjadi blur/kabur
- **Focus_Trap**: Mekanisme yang menjaga fokus keyboard tetap di dalam dialog

## Requirements

### Requirement 1: Backdrop Visual Enhancement

**User Story:** Sebagai user, saya ingin konten di belakang popup menjadi blur, sehingga saya dapat fokus pada dialog tanpa terdistraksi oleh konten di belakangnya.

#### Acceptance Criteria

1. WHEN a dialog is opened, THE Backdrop SHALL display a blur effect on the underlying content
2. WHEN a dialog is opened, THE Backdrop SHALL have a darkened semi-transparent overlay
3. THE Backdrop SHALL use backdrop-filter CSS with blur effect
4. THE Backdrop SHALL have smooth transition animation when appearing and disappearing
5. WHEN the backdrop is visible, THE underlying content SHALL be visually de-emphasized

### Requirement 2: Dialog Display Fix

**User Story:** Sebagai user, saya ingin dialog popup muncul dengan proper z-index dan positioning, sehingga popup tidak "tenggelam" atau tertutup oleh elemen lain.

#### Acceptance Criteria

1. THE Dialog SHALL have z-index higher than all other page elements
2. WHEN a dialog is opened, THE Dialog SHALL be centered both vertically and horizontally
3. THE Dialog SHALL use flexbox for proper centering
4. WHEN multiple dialogs are opened sequentially, THE current Dialog SHALL always be on top
5. THE Dialog SHALL have drop shadow to create depth separation from backdrop

### Requirement 3: Smooth Animation

**User Story:** Sebagai user, saya ingin dialog muncul dan menghilang dengan animasi yang smooth, sehingga transisi terasa natural dan tidak jarring.

#### Acceptance Criteria

1. WHEN a dialog is opened, THE Dialog SHALL fade in with opacity transition
2. WHEN a dialog is opened, THE Dialog SHALL scale up slightly from 95% to 100%
3. WHEN a dialog is closed, THE Dialog SHALL fade out and scale down
4. THE Backdrop SHALL fade in and out synchronized with dialog animation
5. ALL transitions SHALL complete within 200-300ms duration

### Requirement 4: Accessibility Improvements

**User Story:** Sebagai user yang menggunakan keyboard, saya ingin fokus tetap terjaga di dalam dialog, sehingga saya tidak accidentally berinteraksi dengan konten di belakangnya.

#### Acceptance Criteria

1. WHEN a dialog is opened, THE System SHALL prevent scroll on the body element
2. WHEN a dialog is closed, THE System SHALL restore scroll capability on body element
3. WHEN a dialog is opened, THE first focusable element SHALL receive focus automatically
4. WHEN Tab key is pressed at last element, THE focus SHALL move to first focusable element (focus trap)
5. WHEN Escape key is pressed, THE Dialog SHALL close (already implemented)

### Requirement 5: Cross-browser Compatibility

**User Story:** Sebagai user dengan berbagai browser, saya ingin backdrop blur effect bekerja konsisten, sehingga pengalaman visual tetap baik di semua browser.

#### Acceptance Criteria

1. THE Backdrop_Blur SHALL use backdrop-filter with -webkit- prefix for Safari support
2. WHEN backdrop-filter is not supported, THE Backdrop SHALL fallback to darker overlay
3. THE Dialog SHALL render correctly in Chrome, Firefox, Safari, and Edge
4. ALL CSS animations SHALL use GPU acceleration with transform and opacity
5. THE System SHALL not cause layout shift or reflow when dialog opens

