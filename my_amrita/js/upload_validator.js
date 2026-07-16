document.addEventListener("DOMContentLoaded", function() {
    // 100 GB limit in bytes
    const maxFileSize = 100 * 1024 * 1024 * 1024;
    
    // Find all forms with file inputs
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const fileInputs = form.querySelectorAll('input[type="file"]');
        if (fileInputs.length > 0) {
            form.addEventListener('submit', function(e) {
                let isOversized = false;
                
                fileInputs.forEach(input => {
                    if (input.files) {
                        for (let i = 0; i < input.files.length; i++) {
                            if (input.files[i].size > maxFileSize) {
                                isOversized = true;
                            }
                        }
                    }
                });
                
                if (isOversized) {
                    e.preventDefault();
                    alert("Upload Error: One or more files exceed the maximum allowed size of 100 GB. This file type or size is not supported.");
                }
            });
            
            // Also append a visual note about the max file size near the submit button or file input
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                const note = document.createElement('div');
                note.style.fontSize = '11px';
                note.style.color = '#888';
                note.style.marginTop = '4px';
                note.style.marginBottom = '8px';
                note.innerHTML = '<i class="fa fa-info-circle"></i> Max file size limit: 100 GB';
                submitBtn.parentNode.insertBefore(note, submitBtn);
            }
        }
    });
});
