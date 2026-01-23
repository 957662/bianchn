/**
 * 小伍3D查看器 - Three.js实现
 */

(function() {
    'use strict';

    /**
     * 3D查看器类
     */
    class XiaowuViewer {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            if (!this.container) {
                console.error('查看器容器不存在:', containerId);
                return;
            }

            this.config = JSON.parse(this.container.dataset.config || '{}');
            this.scene = null;
            this.camera = null;
            this.renderer = null;
            this.controls = null;
            this.model = null;
            this.animationId = null;
            this.isRotating = this.config.autoRotate || false;

            this.init();
        }

        /**
         * 初始化查看器
         */
        init() {
            if (typeof THREE === 'undefined') {
                this.showError('Three.js未加载');
                return;
            }

            try {
                this.setupScene();
                this.setupCamera();
                this.setupRenderer();
                this.setupLights();
                this.setupControls();
                this.loadModel();
                this.bindEvents();
                this.animate();

                // 响应式调整
                window.addEventListener('resize', () => this.onWindowResize());
            } catch (error) {
                console.error('初始化查看器失败:', error);
                this.showError('初始化失败: ' + error.message);
            }
        }

        /**
         * 设置场景
         */
        setupScene() {
            this.scene = new THREE.Scene();
            const bgColor = this.config.backgroundColor || '#ffffff';
            this.scene.background = new THREE.Color(bgColor);
        }

        /**
         * 设置相机
         */
        setupCamera() {
            const width = this.container.clientWidth;
            const height = this.container.clientHeight;

            this.camera = new THREE.PerspectiveCamera(
                45,
                width / height,
                0.1,
                1000
            );

            const camPos = this.config.cameraPosition || { x: 0, y: 0, z: 5 };
            this.camera.position.set(camPos.x, camPos.y, camPos.z);
        }

        /**
         * 设置渲染器
         */
        setupRenderer() {
            this.renderer = new THREE.WebGLRenderer({
                antialias: true,
                alpha: true
            });

            this.renderer.setSize(
                this.container.clientWidth,
                this.container.clientHeight
            );
            this.renderer.setPixelRatio(window.devicePixelRatio);
            this.renderer.outputEncoding = THREE.sRGBEncoding;
            this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
            this.renderer.toneMappingExposure = 1;

            this.container.appendChild(this.renderer.domElement);
        }

        /**
         * 设置灯光
         */
        setupLights() {
            const lighting = this.config.lighting || 'studio';

            switch (lighting) {
                case 'studio':
                    // 工作室灯光 - 三点布光
                    const keyLight = new THREE.DirectionalLight(0xffffff, 1);
                    keyLight.position.set(5, 5, 5);
                    this.scene.add(keyLight);

                    const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
                    fillLight.position.set(-5, 0, -5);
                    this.scene.add(fillLight);

                    const backLight = new THREE.DirectionalLight(0xffffff, 0.3);
                    backLight.position.set(0, 5, -5);
                    this.scene.add(backLight);
                    break;

                case 'natural':
                    // 自然光
                    const sunLight = new THREE.DirectionalLight(0xffffff, 1.2);
                    sunLight.position.set(10, 10, 5);
                    this.scene.add(sunLight);

                    const skyLight = new THREE.HemisphereLight(0x87ceeb, 0x545454, 0.6);
                    this.scene.add(skyLight);
                    break;

                case 'dark':
                    // 暗光环境
                    const spotLight = new THREE.SpotLight(0xffffff, 1);
                    spotLight.position.set(0, 10, 0);
                    spotLight.angle = Math.PI / 4;
                    this.scene.add(spotLight);
                    break;

                default:
                    // 默认环境光
                    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
                    this.scene.add(ambientLight);

                    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
                    dirLight.position.set(5, 5, 5);
                    this.scene.add(dirLight);
            }

            // 始终添加环境光
            const ambient = new THREE.AmbientLight(0xffffff, 0.4);
            this.scene.add(ambient);
        }

        /**
         * 设置控制器
         */
        setupControls() {
            if (typeof THREE.OrbitControls === 'undefined') {
                console.warn('OrbitControls未加载');
                return;
            }

            this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.05;
            this.controls.enableZoom = this.config.enableZoom !== false;
            this.controls.enablePan = this.config.enablePan !== false;
            this.controls.autoRotate = this.isRotating;
            this.controls.autoRotateSpeed = this.config.rotateSpeed || 1.0;
        }

        /**
         * 加载模型
         */
        loadModel() {
            const modelUrl = this.config.modelUrl;
            const modelFormat = this.config.modelFormat || 'glb';

            if (!modelUrl) {
                this.showError('模型URL未指定');
                return;
            }

            this.showLoading(true);

            let loader;
            switch (modelFormat.toLowerCase()) {
                case 'gltf':
                case 'glb':
                    if (typeof THREE.GLTFLoader === 'undefined') {
                        this.showError('GLTFLoader未加载');
                        return;
                    }
                    loader = new THREE.GLTFLoader();
                    break;

                case 'obj':
                    if (typeof THREE.OBJLoader === 'undefined') {
                        this.showError('OBJLoader未加载');
                        return;
                    }
                    loader = new THREE.OBJLoader();
                    break;

                case 'fbx':
                    if (typeof THREE.FBXLoader === 'undefined') {
                        this.showError('FBXLoader未加载');
                        return;
                    }
                    loader = new THREE.FBXLoader();
                    break;

                default:
                    this.showError('不支持的模型格式: ' + modelFormat);
                    return;
            }

            loader.load(
                modelUrl,
                (result) => this.onModelLoaded(result),
                (progress) => this.onLoadProgress(progress),
                (error) => this.onLoadError(error)
            );
        }

        /**
         * 模型加载完成
         */
        onModelLoaded(result) {
            this.showLoading(false);

            // GLTF格式返回包含scene的对象
            if (result.scene) {
                this.model = result.scene;
            } else {
                this.model = result;
            }

            // 居中模型
            const box = new THREE.Box3().setFromObject(this.model);
            const center = box.getCenter(new THREE.Vector3());
            this.model.position.sub(center);

            // 调整相机距离
            const size = box.getSize(new THREE.Vector3());
            const maxDim = Math.max(size.x, size.y, size.z);
            const fov = this.camera.fov * (Math.PI / 180);
            let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
            cameraZ *= 1.5; // 增加一些间距

            this.camera.position.z = cameraZ;
            this.camera.lookAt(0, 0, 0);

            if (this.controls) {
                this.controls.update();
            }

            this.scene.add(this.model);

            // 记录浏览次数
            this.recordView();
        }

        /**
         * 加载进度
         */
        onLoadProgress(progress) {
            if (progress.lengthComputable) {
                const percent = (progress.loaded / progress.total * 100).toFixed(0);
                const loadingText = this.container.querySelector('.xiaowu-3d-loading p');
                if (loadingText) {
                    loadingText.textContent = `加载中... ${percent}%`;
                }
            }
        }

        /**
         * 加载错误
         */
        onLoadError(error) {
            console.error('加载模型失败:', error);
            this.showLoading(false);
            this.showError('加载模型失败: ' + error.message);
        }

        /**
         * 绑定事件
         */
        bindEvents() {
            const containerElement = this.container.closest('.xiaowu-3d-viewer-container');
            if (!containerElement) return;

            // 重置相机
            const resetBtn = containerElement.querySelector('[data-action="reset-camera"]');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => this.resetCamera());
            }

            // 切换旋转
            const rotateBtn = containerElement.querySelector('[data-action="toggle-rotate"]');
            if (rotateBtn) {
                rotateBtn.addEventListener('click', () => this.toggleRotate(rotateBtn));
            }

            // 全屏
            const fullscreenBtn = containerElement.querySelector('[data-action="toggle-fullscreen"]');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
            }

            // 截图
            const screenshotBtn = containerElement.querySelector('[data-action="screenshot"]');
            if (screenshotBtn) {
                screenshotBtn.addEventListener('click', () => this.takeScreenshot());
            }
        }

        /**
         * 动画循环
         */
        animate() {
            this.animationId = requestAnimationFrame(() => this.animate());

            if (this.controls) {
                this.controls.update();
            }

            this.renderer.render(this.scene, this.camera);
        }

        /**
         * 重置相机
         */
        resetCamera() {
            const camPos = this.config.cameraPosition || { x: 0, y: 0, z: 5 };
            this.camera.position.set(camPos.x, camPos.y, camPos.z);
            this.camera.lookAt(0, 0, 0);

            if (this.controls) {
                this.controls.reset();
            }
        }

        /**
         * 切换旋转
         */
        toggleRotate(button) {
            this.isRotating = !this.isRotating;

            if (this.controls) {
                this.controls.autoRotate = this.isRotating;
            }

            if (button) {
                button.classList.toggle('active', this.isRotating);
            }
        }

        /**
         * 全屏切换
         */
        toggleFullscreen() {
            const containerElement = this.container.closest('.xiaowu-3d-viewer-container');
            if (!containerElement) return;

            if (!document.fullscreenElement) {
                containerElement.requestFullscreen().catch(err => {
                    console.error('全屏失败:', err);
                });
                containerElement.classList.add('fullscreen');
            } else {
                document.exitFullscreen();
                containerElement.classList.remove('fullscreen');
            }

            // 延迟调整大小
            setTimeout(() => this.onWindowResize(), 100);
        }

        /**
         * 截图
         */
        takeScreenshot() {
            this.renderer.render(this.scene, this.camera);
            const dataUrl = this.renderer.domElement.toDataURL('image/png');

            const link = document.createElement('a');
            link.download = 'xiaowu-3d-model-' + Date.now() + '.png';
            link.href = dataUrl;
            link.click();
        }

        /**
         * 窗口大小调整
         */
        onWindowResize() {
            const width = this.container.clientWidth;
            const height = this.container.clientHeight;

            this.camera.aspect = width / height;
            this.camera.updateProjectionMatrix();

            this.renderer.setSize(width, height);
        }

        /**
         * 显示加载状态
         */
        showLoading(show) {
            const loading = this.container.closest('.xiaowu-3d-viewer-container')
                ?.querySelector('.xiaowu-3d-loading');

            if (loading) {
                if (show) {
                    loading.classList.remove('hidden');
                } else {
                    loading.classList.add('hidden');
                }
            }
        }

        /**
         * 显示错误
         */
        showError(message) {
            const errorElement = this.container.closest('.xiaowu-3d-viewer-container')
                ?.querySelector('.xiaowu-3d-error-message');

            if (errorElement) {
                errorElement.innerHTML = `
                    <h3>加载失败</h3>
                    <p>${message}</p>
                `;
                errorElement.style.display = 'block';
            }

            this.showLoading(false);
        }

        /**
         * 记录浏览次数
         */
        recordView() {
            const modelId = this.container.dataset.modelId;
            if (!modelId) return;

            // 调用REST API记录浏览
            fetch(`/wp-json/xiaowu-3d/v1/models/${modelId}/view`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            }).catch(error => {
                console.error('记录浏览失败:', error);
            });
        }

        /**
         * 销毁查看器
         */
        destroy() {
            if (this.animationId) {
                cancelAnimationFrame(this.animationId);
            }

            if (this.renderer) {
                this.renderer.dispose();
            }

            if (this.controls) {
                this.controls.dispose();
            }

            window.removeEventListener('resize', () => this.onWindowResize());
        }
    }

    /**
     * 全局初始化函数
     */
    window.XiaowuViewer = {
        init: function(containerId) {
            return new XiaowuViewer(containerId);
        },

        /**
         * 初始化所有查看器
         */
        initAll: function() {
            const viewers = document.querySelectorAll('.xiaowu-3d-viewer[data-config]');
            const instances = [];

            viewers.forEach(viewer => {
                if (viewer.id) {
                    instances.push(new XiaowuViewer(viewer.id));
                }
            });

            return instances;
        }
    };

    // 页面加载完成后自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.XiaowuViewer.initAll();
        });
    } else {
        window.XiaowuViewer.initAll();
    }

})();
