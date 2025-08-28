// PasteUploadAdapter.js
// Custom Upload Adapter for CKEditor 5 to auto-upload pasted/dragged images.
// Place this file at: CKEditor5/js/pasteUploadAdapter.js
(function () {
  class PasteUploadAdapter {
    constructor(loader) {
      this.loader = loader;
      this.xhr = null;
      // Default endpoint for uploads. Override globally before init with:
      // window.PasteUploadAdapterUploadUrl = '/your/custom/upload.php';
      this.uploadUrl = window.PasteUploadAdapterUploadUrl || '/editors/CKeditor5/upload.php';
    }

    upload() {
      return this.loader.file.then(file => new Promise((resolve, reject) => {
        this._initRequest();
        this._initListeners(resolve, reject, file);
        this._sendRequest(file);
      }));
    }

    abort() {
      if (this.xhr) {
        this.xhr.abort();
      }
    }

    _initRequest() {
      const xhr = this.xhr = new XMLHttpRequest();
      xhr.open('POST', this.uploadUrl, true);
      xhr.responseType = 'json';
      // If you need to pass auth or CSRF headers, set them here, e.g.:
      // xhr.setRequestHeader('X-CSRF-Token', window.CKEditorCsrfToken || '');
    }

    _initListeners(resolve, reject, file) {
      const xhr = this.xhr;
      const loader = this.loader;

      xhr.addEventListener('error', () => reject('Upload failed.'));
      xhr.addEventListener('abort', () => reject('Upload aborted.'));
      xhr.addEventListener('load', () => {
        const response = xhr.response;

        if (!response) {
          return reject('No response from server.');
        }

        if (response.error) {
          return reject(response.error.message || 'Upload failed.');
        }

        // Try common response shapes. CKEditor expects an object with `default` (or `url`).
        const url = response.url || response.fileUrl || (response.data && response.data.url);
        if (!url) {
          return reject('Upload succeeded but no file URL returned by server.');
        }

        resolve({
          default: url
        });
      });

      if (xhr.upload) {
        xhr.upload.addEventListener('progress', evt => {
          if (evt.lengthComputable) {
            loader.uploadTotal = evt.total;
            loader.uploaded = evt.loaded;
          }
        });
      }
    }

    _sendRequest(file) {
      const data = new FormData();
      // The PHP handler in this repo typically expects 'upload' - match that.
      data.append('upload', file, file.name);

      // Add any extra fields required by your server here:
      // data.append('folder', 'articles');

      this.xhr.send(data);
    }
  }

  // Expose globally so the init script can create instances.
  window.PasteUploadAdapter = PasteUploadAdapter;
})();
