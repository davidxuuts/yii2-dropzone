if (typeof Dropzone !== 'undefined' || Dropzone) {
    Dropzone.autoDiscover = false;
}

function dropzoneInit (myDropzone, fieldEl, existFiles, isStoreInDB, uploadDrive) {
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

    if (uploadDrive === 'local') {
        myDropzone.options.chunksUploaded = function (file, done) {
            const { responseText } = file.xhr
            let response = (typeof responseText === 'string') ? JSON.parse(responseText) : responseText
            const { success, completed, data } = response
            if (success && completed) {
                data.eof = true
                $.ajax({
                    type: 'POST',
                    url: myDropzone.options.url,
                    data: data,
                    success: function (res) {
                        file.status = Dropzone.SUCCESS
                        myDropzone.emit('success', file, res);
                        // done();
                    },
                    error: function (msg) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            title: myDropzone.options.dictResponseError,
                            showConfirmButton: false,
                            icon: 'error'
                        })
                    }
                });
            } else {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    title: myDropzone.options.dictResponseError,
                    showConfirmButton: false,
                    icon: 'error'
                })
            }
        }
    }
}

function dropzoneEvents(
    myDropzone,
    fieldEl,
    metaData,
    isStoreInDB,
    uploadBasePath,
    uploadDrive,
    qiniuToken,
    secondUpload,
    getHashUrl
) {
    myDropzone.on("addedfile", function (file) {
        if (secondUpload) {
            getHash(file).then(hash => {
                $.ajax({
                    data: {hash: hash},
                    url: getHashUrl,
                    type: 'POST',
                    success: function (response) {
                        const {success} = response
                        if (success === true || success === 'true') {
                            myDropzone.emit('success', file, response)
                        } else {
                            handleUpload(myDropzone, file, uploadBasePath, metaData, uploadDrive, qiniuToken)
                        }
                    }
                })
            })
        } else {
            handleUpload(myDropzone, file, uploadBasePath, metaData, uploadDrive, qiniuToken)
        }
    })
    myDropzone.on("addedfiles", function () {
        if (myDropzone.files.length >= myDropzone.options.maxFiles) {
            $(myDropzone.options.previewsContainer).find('.fileinput-button').parent().addClass('none')
        }
        if (uploadDrive === 'local') {
            myDropzone.processQueue()
        }
    })
    myDropzone.on('sending', function (file, xhr, formData) {
        const fileInfo = getFileInfo(file, uploadBasePath)
        $.each(metaData, function (mtKey, value) {
            formData.append(mtKey, value)
        })
        $.each(fileInfo, function (k, value) {
            formData.append(k, value)
        })
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
    myDropzone.on('success', function (file, response) {
        const { success, data } = response
        if (success === true || success === 'true') {
            if (isStoreInDB === true) {
                if (myDropzone.options.maxFiles > 1) {
                    let value = fieldEl.val()
                    let valueArray = value.split(',')
                    if (valueArray.includes('0')) {
                        valueArray.remove('0')
                    }
                    valueArray.push(data.id)
                    fieldEl.val(valueArray.toString())
                } else {
                    fieldEl.val(data.id)
                }
            } else {
                if (myDropzone.options.maxFiles > 1) {
                    let value = fieldEl.val()
                    let valueArray = value.split(',')
                    valueArray.push(data.path)
                    fieldEl.val(valueArray.toString())
                } else {
                    fieldEl.val(data.path)
                }
            }
            fieldEl.parent().find('.progress').attr({"style": "opacity:0;"})
        } else {
            myDropzone.removeFile(file)
            Swal.fire({
                toast: true,
                position: 'top-end',
                title: data,
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
                if (this[i] !== v) {
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
        fieldEl.parent().find('.fileinput-button').parent().removeClass('none')
    })
}

function handleUpload (myDropzone, file, uploadBasePath, metaData, uploadDrive, qiniuToken) {
    const { file_type, key, name, mime_type } = getFileInfo(file, uploadBasePath)
    const formData = {}
    $.each(metaData, function (mtKey, value) {
        formData[mtKey] = value
    })
    if (uploadDrive.toLowerCase() === 'qiniu') {
        formData['x:file_type'] = file_type
        const config = {
            useCdnDomain: true,
            // chunkSize: Math.floor({$this->chunkSize},  1024 * 1024)
        }
        const putExtra = {
            fname: name,
            mimeType: mime_type,
            customVars: formData
        }
        const observable = qiniu.upload(file, key, qiniuToken, putExtra, config)
        const observer = {
            next(res) {
                // const percent = res.total.percent.toFixed(2)
                // console.log('upload percent', percent)
            },
            error(err) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    title: myDropzone.options.dictResponseError,
                    showConfirmButton: false,
                    icon: 'error'
                })
            },
            complete(res) {
                myDropzone.options.autoProcessQueue = false;
                myDropzone.emit('success', file, res)
            }
        }
        const subscription = observable.subscribe(observer)
    }
}
