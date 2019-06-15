INTRODUCTION
============
Alternative UI for Media Directories module, providing classical file browsing interface.


REQUIREMENTS
============
- Drupal Core 8.7+
- Entity browser
- jstree library


NOTES
============
Upload widget uses code from core with some overrides.
We don't use separate library builder or state service, at least for now, in the future
it should use core classes instead of duplicating them. The main reason for this is
that it forces library view, but we really don't need it, and working around of it at first was a bit difficult.


ROADMAP
=============
- Drag and Drop not yet working (need to migrate existing code).
- CKEditor and field widget integration (need to migrate existing code).
- Check if other custom media types are working or if they don't, find out how to make them work.
- Need to review permissions, add settings.
- We need some kind of preview functionality, maybe some UI changes are needed.
- Quick file upload without type selection (match first media type which allows this file type).
- I'm not sure if upload widget should be inside modal, maybe we could just display it inside file listing area.
- jstree library need to be installed manually.
- Coding standards and code cleanup.
- Maybe we can drop entity browser requirement (required only for ckeditor integration).
