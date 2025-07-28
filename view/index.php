<?php view_header('API文档'); ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">API文档</h5>
                    <button class="btn btn-primary" onclick="generateDocs()">重新生成文档</button>
                </div>
                <div class="card-body p-0">
                    <div id="swagger-ui"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
<link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />

<script>
function generateDocs() {
    ajax('/swagger_ui/site/generate', {}, function(res) {
        if (res.code === 0) {
            layer.msg('文档生成成功', {icon: 1});
            // 重新加载Swagger UI
            initSwaggerUI();
        } else {
            layer.msg('文档生成失败', {icon: 2});
        }
    });
}

function initSwaggerUI() {
    SwaggerUIBundle({
        url: '/openapi.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        plugins: [
            SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout"
    });
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function() {
    initSwaggerUI();
});
</script>

<?php view_footer(); ?>