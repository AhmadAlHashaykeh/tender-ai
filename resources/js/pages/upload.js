// Excel file dropzone (Section 1)
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('file-upload');
const fileInfoContainer = document.getElementById('fileInfoContainer');
const fileNameDisplay = document.getElementById('fileName');
const fileSizeDisplay = document.getElementById('fileSize');
const removeFileBtn = document.getElementById('removeFile');
const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');

if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('highlight'), false);
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('highlight'), false);
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (files?.length) {
            fileInput.files = files;
            handleFiles(files);
        }
    });

    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

    const handleFiles = (files) => {
        if (!files?.length) {
            return;
        }
        const file = files[0];
        if (fileNameDisplay) {
            fileNameDisplay.innerText = file.name;
        }
        if (fileSizeDisplay) {
            fileSizeDisplay.innerText = `${(file.size / 1024).toFixed(1)} KB`;
        }
        fileInfoContainer?.classList.add('active');
        uploadSubmitBtn?.removeAttribute('disabled');
    };

    removeFileBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.value = '';
        fileInfoContainer?.classList.remove('active');
        uploadSubmitBtn?.setAttribute('disabled', 'true');
    });
}
