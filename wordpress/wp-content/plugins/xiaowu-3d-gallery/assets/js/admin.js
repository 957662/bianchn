/**
 * 小伍3D图库 - 管理后台JS
 */

(function($) {
    'use strict';

    /**
     * 3D图库管理
     */
    const Xiaowu3DAdmin = {
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initUploader();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            // 分类筛选
            $(document).on('change', '#filter-category', function() {
                $(this).closest('form').submit();
            });

            // 批量操作
            $(document).on('change', '.check-column input[type="checkbox"]', function() {
                const checked = $(this).is(':checked');
                if ($(this).closest('th').length) {
                    // 全选/取消全选
                    $('.check-column input[type="checkbox"]').prop('checked', checked);
                }
            });
        },

        /**
         * 初始化上传器
         */
        initUploader: function() {
            const uploadArea = $('.xiaowu-3d-upload-area');
            if (!uploadArea.length) return;

            const fileInput = $('#model-file-input');

            // 点击上传
            uploadArea.on('click', function(e) {
                if (!$(e.target).is('input')) {
                    fileInput.click();
                }
            });

            // 文件选择
            fileInput.on('change', function() {
                const files = this.files;
                if (files.length > 0) {
                    Xiaowu3DAdmin.uploadFile(files[0]);
                }
            });

            // 拖拽上传
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragging');
            });

            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragging');
            });

            uploadArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragging');

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    Xiaowu3DAdmin.uploadFile(files[0]);
                }
            });
        },

        /**
         * 上传文件
         */
        uploadFile: function(file) {
            // 验证文件
            const allowedFormats = ['glb', 'gltf', 'obj', 'fbx'];
            const maxSize = 100 * 1024 * 1024; // 100MB

            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowedFormats.includes(ext)) {
                alert('不支持的文件格式。支持的格式: ' + allowedFormats.join(', '));
                return;
            }

            if (file.size > maxSize) {
                alert('文件太大。最大支持100MB');
                return;
            }

            // 准备上传
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', file.name.replace(/\.[^/.]+$/, ''));

            // 显示进度
            this.showProgress(true);
            this.updateProgress(0);

            // 上传
            $.ajax({
                url: '/wp-json/xiaowu-3d/v1/models/upload',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': xiaowu3D.nonce
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            Xiaowu3DAdmin.updateProgress(percent);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    Xiaowu3DAdmin.showProgress(false);

                    if (response.success) {
                        Xiaowu3DAdmin.showNotice('上传成功!', 'success');
                        setTimeout(function() {
                            window.location.href = '/wp-admin/post.php?post=' + response.model_id + '&action=edit';
                        }, 1000);
                    } else {
                        Xiaowu3DAdmin.showNotice('上传失败: ' + response.message, 'error');
                    }
                },
                error: function(xhr) {
                    Xiaowu3DAdmin.showProgress(false);
                    Xiaowu3DAdmin.showNotice('上传失败: ' + xhr.statusText, 'error');
                }
            });
        },

        /**
         * 显示进度条
         */
        showProgress: function(show) {
            let progressBar = $('.xiaowu-3d-progress');

            if (show && !progressBar.length) {
                progressBar = $('<div class="xiaowu-3d-progress"><div class="xiaowu-3d-progress-bar">0%</div></div>');
                $('.xiaowu-3d-upload-area').after(progressBar);
            } else if (!show && progressBar.length) {
                progressBar.remove();
            }
        },

        /**
         * 更新进度
         */
        updateProgress: function(percent) {
            const progressBar = $('.xiaowu-3d-progress-bar');
            if (progressBar.length) {
                progressBar.css('width', percent + '%');
                progressBar.text(Math.round(percent) + '%');
            }
        },

        /**
         * 显示通知
         */
        showNotice: function(message, type = 'info') {
            const notice = $('<div>')
                .addClass('xiaowu-3d-notice')
                .addClass(type)
                .text(message);

            $('.wrap').prepend(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * 预览模型
         */
        previewModel: function(modelId) {
            // 加载模型数据
            $.ajax({
                url: `/wp-json/xiaowu-3d/v1/models/${modelId}`,
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        Xiaowu3DAdmin.showPreviewModal(response.data);
                    }
                },
                error: function() {
                    Xiaowu3DAdmin.showNotice('加载模型失败', 'error');
                }
            });
        },

        /**
         * 显示预览模态框
         */
        showPreviewModal: function(modelData) {
            const viewerConfig = modelData.viewer_config ? JSON.parse(modelData.viewer_config) : {};
            const viewerId = 'preview-viewer-' + Date.now();

            const modal = $('<div>')
                .addClass('xiaowu-3d-modal')
                .html(`
                    <div class="xiaowu-3d-modal-content" style="max-width: 1000px;">
                        <span class="xiaowu-3d-modal-close">&times;</span>
                        <h2>${modelData.title}</h2>
                        <div class="xiaowu-3d-viewer-container" style="width: 100%; height: 600px; margin: 20px 0;">
                            <div id="${viewerId}" class="xiaowu-3d-viewer"
                                 data-config='${JSON.stringify({
                                     modelUrl: modelData.file_url,
                                     modelFormat: modelData.file_format,
                                     ...viewerConfig
                                 })}'></div>
                            <div class="xiaowu-3d-loading">
                                <div class="xiaowu-3d-spinner"></div>
                                <p>加载模型中...</p>
                            </div>
                            <div class="xiaowu-3d-error-message" style="display: none;"></div>
                        </div>
                        <div class="xiaowu-3d-card">
                            <p><strong>格式:</strong> ${modelData.file_format.toUpperCase()}</p>
                            <p><strong>浏览量:</strong> ${modelData.view_count || 0}</p>
                            ${modelData.description ? `<p>${modelData.description}</p>` : ''}
                        </div>
                    </div>
                `);

            $('body').append(modal);

            // 初始化查看器
            if (typeof XiaowuViewer !== 'undefined') {
                setTimeout(function() {
                    XiaowuViewer.init(viewerId);
                }, 100);
            }

            // 关闭模态框
            modal.find('.xiaowu-3d-modal-close').on('click', function() {
                modal.fadeOut(function() {
                    modal.remove();
                });
            });

            modal.on('click', function(e) {
                if ($(e.target).is('.xiaowu-3d-modal')) {
                    modal.fadeOut(function() {
                        modal.remove();
                    });
                }
            });
        }
    };

    /**
     * 文档加载完成后初始化
     */
    $(document).ready(function() {
        Xiaowu3DAdmin.init();
    });

    // 暴露到全局
    window.Xiaowu3DAdmin = Xiaowu3DAdmin;

})(jQuery);
