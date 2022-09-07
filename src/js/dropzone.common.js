Dropzone.autoDiscover = false

jQuery(function ($) {
    const cropModal = '<div class="modal fade" id="dropzone-modal" aria-hidden="true" data-backdrop="static" style="display: none;" tabindex="-1">'
        + '    <div class="modal-dialog">'
        + '        <div class="modal-content">'
        + '            <div class="modal-header">'
        + '                <button type="button" class="close" data-dismiss="modal" aria-label="Close">'
        + '                    <span aria-hidden="true">×</span>'
        + '                </button>'
        + '            </div>'
        + '            <div class="modal-body">'
        + '                <div class="img-container">'
        + '                    <p>Loading ...</p>'
        + '                </div>'
        + '            </div>'
        + '            <div class="modal-footer">'
        + '                <button class="btn btn-secondary" data-dismiss="modal">关闭</button>'
        + '                <button type="submit" class="btn btn-primary">OK</button>'
        + '            </div>'
        + '        </div>'
        + '    </div>'
        + '</div>'

    $('section.content').append(cropModal)
})

const getHash = function (file) {
    return new Promise(function(resolve, reject) {
        let hash = ''
        let reader = new FileReader()
        reader.readAsArrayBuffer(file)
        reader.onload = () => {
            hash = getEtag(reader.result)
            resolve(hash)
        }
    })
}

function init (myDropzone, fieldEl, existFiles, isStoreInDB) {
    let typeOfExistFiles = 'undefined'
    if (Object.prototype.toString.call(existFiles) === '[object Array]') {
        typeOfExistFiles = 'array'
    }
    if (Object.prototype.toString.call(existFiles) === '[object Object]') {
        typeOfExistFiles = 'object'
    }
    // Is array
    if (typeOfExistFiles === 'array' && existFiles.length > 0) {
        let valueArray = []
        existFiles.map(function(existFile) {
            if (Object.prototype.toString.call(existFile) === '[object Object]'
                && existFile.hasOwnProperty('name')
                && existFile.hasOwnProperty('size')
                && existFile.hasOwnProperty('path')
            ) {
                myDropzone.displayExistingFile(existFile, existFile.path)
                if (isStoreInDB) {
                    valueArray.push(existFile.id)
                } else {
                    valueArray.push(existFile.path)
                }
                fieldEl.parent().find('.progress').attr({"style":"opacity:0;"})
            }
        })
        fieldEl.val(valueArray.toString())
    }
    // Is object
    if (
        typeOfExistFiles === 'object'
        && existFiles.hasOwnProperty('name')
        && existFiles.hasOwnProperty('size')
        && existFiles.hasOwnProperty('path')
    ) {
        myDropzone.displayExistingFile(existFiles, existFiles.path)
        if (isStoreInDB) {
            fieldEl.val(existFiles.id)
        } else {
            fieldEl.val(existFiles.path)
        }
        fieldEl.parent().find('.progress').attr({"style":"opacity:0;"})
    }
    if (
        (typeOfExistFiles === 'array' && existFiles.length >= myDropzone.options.maxFiles)
        || (typeOfExistFiles === 'object' && myDropzone.options.maxFiles <= 1)
    ) {
        $(myDropzone.options.previewsContainer).find('.fileinput-button').parent().addClass('none')
    }
}

function dropzoneEvents(myDropzone, fieldEl, metaData, isStoreInDB, uploadBasePath, fileKey, driveIsQiniu, qiniuToken) {
    Array.prototype.indexOf = function (val) {
        for (let i = 0; i < this.length; i++) {
            if (this[i] === val) return i;
        }
        return -1;
    }
    Array.prototype.remove = function (val) {
        let index = this.indexOf(val);
        if (index > -1) {
            this.splice(index, 1);
        }
    }
    myDropzone.on("addedfile", function (file) {
        // if (!crop) {
        //     getHash(file).then(hash => {
        //         console.log('file hash', hash)
        //     })
        // }
    })
    myDropzone.on("addedfiles", function () {
        if (myDropzone.files.length >= myDropzone.options.maxFiles) {
            $(myDropzone.options.previewsContainer).find('.fileinput-button').parent().addClass('none')
        }
    })
    myDropzone.on('sending', function (file, xhr, formData) {
        $.each(metaData, function (key, value) {
            formData.append(key, value)
        })
        let extension = file.name.substr(file.name.lastIndexOf('.'))
        const mimeType = file.type.split('/', 1)[0]
        let fileType = 'others'
        if (mimeType === 'image') {
            fileType = 'images'
        } else if (mimeType === 'video') {
            fileType = 'videos'
        } else if (mimeType === 'audio') {
            fileType = 'audios'
        }
        formData.append('key', uploadBasePath + fileType + '/' + fileKey + extension)
        if (driveIsQiniu) {
            formData.append('x:file_type', fileType)
            formData.append('token', qiniuToken)
        }
    })
    myDropzone.on('error', (file, message) => {
        myDropzone.removeFile(file)
        Swal.fire({
            toast: true,
            position: 'top-end',
            title: myDropzone.options.dictResponseError,
            showConfirmButton: false,
            icon: 'error'
        })
    })
    // myDropzone.on('uploadprogress', function (file, progress, bytesSent) {
    //     console.log('uploadprogress', file, progress, bytesSent)
    // })
    // myDropzone.on('complete', function (file) {
    //     console.log('complete', file)
    // })
    myDropzone.on('success', function (file, response) {
        console.log(response)
        if (response.success === true || response.success === 'true') {
            if (isStoreInDB) {
                if (myDropzone.options.maxFiles > 1) {
                    let value = fieldEl.val()
                    console.log('158 fieldEl', fieldEl, 'val', value)
                    let valueArray = value.split(',')
                    if (valueArray.includes('0')) {
                        valueArray.remove('0')
                    }
                    valueArray.push(response.result.id)
                    fieldEl.val(valueArray.toString())
                    console.log('165 fieldEl', fieldEl, 'val', value)
                } else {
                    fieldEl.val(response.result.id)
                    console.log('167, fieldEl', fieldEl, 'val', fieldEl.val())
                }
            } else {
                if (myDropzone.options.maxFiles > 1) {
                    let value = fieldEl.val()
                    console.log('173, fieldEl', fieldEl, 'val', value)
                    let valueArray = value.split(',')
                    valueArray.push(response.result.path)
                    fieldEl.val(valueArray.toString())
                    console.log('177, fieldEl', fieldEl, 'val', value)
                } else {
                    fieldEl.val(response.result.path)
                    console.log('fieldEl 180', fieldEl, 'val', fieldEl.val())
                }
            }
            fieldEl.parent().find('.progress').attr({"style": "opacity:0;"})
        } else {
            myDropzone.removeFile(file)
            Swal.fire({
                toast: true,
                position: 'top-end',
                title: response.result,
                showConfirmButton: false,
                icon: 'error'
            })
        }
    })
    myDropzone.on("maxfilesexceeded", function (file) {
        myDropzone.removeFile(file)
        $(myDropzone.options.previewsContainer).find('.fileinput-button').parentNode.addClass('none')
    })
    myDropzone.on("removedfile", function (file) {
        let value = fieldEl.val()
        let valueArray = value.split(',')
        // Add array remove function
        Array.prototype.removeValue = function (v) {
            for (let i = 0, j = 0; i < this.length; i++) {
                if (this[i] != v) {
                    this[j++] = this[i];
                }
            }
            this.length -= 1;
        }

        if (isStoreInDB) {
            valueArray.removeValue(file.id)
        } else {
            valueArray.removeValue(file.path)
        }
        fieldEl.val(valueArray.toString())
        // $(myDropzone.options.previewsContainer).find('.fileinput-button').parent().removeClass('none')
        fieldEl.parent().find('.fileinput-button').parent().removeClass('none')
    })
}

function transformFile(cropOptions) {
    return function (file, done) {
        $('#dropzone-modal').modal('show')
        let submitButton = $('#dropzone-modal').find('button[type="submit"]')
        let myDropZone = this
        submitButton.on('click', function (event) {
            event.preventDefault()
            var canvas = cropper.getCroppedCanvas({width: 256, height: 256})
            canvas.toBlob(function (blob) {
                myDropZone.createThumbnail(
                    blob,
                    myDropZone.options.thumbnailWidth,
                    myDropZone.options.thumbnailHeight,
                    myDropZone.options.thumbnailMethod,
                    false,
                    function (dataURL) {
                        myDropZone.emit('thumbnail', file, dataURL)
                        done(blob)
                    })
            })
            $('#dropzone-modal').modal('hide')
        })
        const image = new Image()
        image.src = URL.createObjectURL(file)

        $('#dropzone-modal').find('.img-container').html(image)
        const cropper = new Cropper(image, cropOptions)
    }
}

