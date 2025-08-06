# CKEditor 5 Integration for ImpressCMS

This is an integration of CKEditor 5 for ImpressCMS. CKEditor 5 is a modern JavaScript rich text editor with a modular architecture.

## Features

- Modern, clean interface
- Responsive design
- Better image handling
- Improved table support
- Customizable toolbar based on user permissions
- Uses CDN for better performance and easier updates

## Requirements

- ImpressCMS 1.4 or higher
- Modern browser (Chrome, Firefox, Safari, Edge)

## Installation

1. Place the CKEditor5 folder below the 'editors' folder of your ImpressCMS installation.
2. To enable it, go to System Admin > Preferences > General Settings and select "CKEditor 5" from the "Default Editor" dropdown.

## Configuration

The editor provides three toolbar configurations based on user permissions:

1. **Basic** - For anonymous users
   - Includes: heading, bold, italic, link, bulletedList, numberedList, undo, redo

2. **Normal** - For registered users
   - Includes: heading, bold, italic, link, bulletedList, numberedList, blockQuote, insertTable, undo, redo

3. **Full** - For administrators
   - Includes: heading, bold, italic, underline, strikethrough, link, bulletedList, numberedList, blockQuote, insertTable, mediaEmbed, undo, redo, alignment, fontColor, fontBackgroundColor, findAndReplace, sourceEditing

## Image Browser

The integration includes a custom image browser that allows users to:

1. Browse existing images
2. Upload new images
3. Select images for insertion into content

## Differences from CKEditor 4

CKEditor 5 is a complete rewrite of CKEditor 4 with a different architecture:

1. It uses a data model instead of operating directly on HTML
2. It has a modular architecture with plugins
3. It uses modern JavaScript (ES6+)
4. It has a different configuration approach
5. It handles images and tables differently

## Credits

- CKEditor 5 is developed by CKSource - https://ckeditor.com/
- This integration was created for ImpressCMS
