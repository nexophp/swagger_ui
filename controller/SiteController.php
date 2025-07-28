<?php 
namespace modules\swagger_ui\controller;

use modules\swagger_ui\lib\OpenApiMdParser;

class SiteController extends \core\AdminController{
    
    /**
     * 生成OpenAPI JSON文档
     */
    public function actionIndex(){
        $this->generateOpenApiJson();
    }
    
    /**
     * 生成OpenAPI JSON文档
     */
    public function actionGenerate(){
        $this->generateOpenApiJson();
        return json(['code' => 0, 'msg' => 'OpenAPI文档生成成功']);
    }
    
    /**
     * 扫描并生成OpenAPI文档
     */
    private function generateOpenApiJson(){
        $openApiData = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'NexoPHP API Documentation',
                'version' => '1.0.0',
                'description' => '自动生成的API文档'
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer'
                    ]
                ]
            ]
        ];
        
        // 扫描所有模块和app目录
        $paths = $this->scanApiFiles();
        
        foreach ($paths as $path) {
            $this->parsePhpFile($path, $openApiData);
        }
        
        // 写入openapi.json文件
        $jsonPath = WWW_PATH . '/openapi.json'; 
        file_put_contents($jsonPath, json_encode($openApiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 扫描所有API相关文件
     */
    private function scanApiFiles(){
        $files = [];
        $modules = get_all_modules();
        foreach($modules as $v){
            $controllerPath = get_dir($v).'/controller'; 
            $controllers = glob($controllerPath . '/Api*.php');
             $files = array_merge($files, $controllers);
        } 
        return $files;
    }
    
    /**
     * 解析PHP文件中的OpenAPI注解
     */
    private function parsePhpFile($filePath, &$openApiData){
        $content = file_get_contents($filePath);
        
        // 使用反射解析OpenAPI注解
        try {
            // 获取类名
            preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch);
            preg_match('/class\s+(\w+)/', $content, $classMatch);
            
            if (!empty($namespaceMatch[1]) && !empty($classMatch[1])) {
                $className = $namespaceMatch[1] . '\\' . $classMatch[1];
                
                if (class_exists($className)) {
                    $reflection = new \ReflectionClass($className);
                    
                    // 解析类级别的注解
                    $this->parseClassAttributes($reflection, $openApiData);
                    
                    // 解析方法级别的注解
                    foreach ($reflection->getMethods() as $method) {
                        $this->parseMethodAttributes($method, $openApiData);
                    }
                }
            }
        } catch (\Exception $e) {
            // 忽略解析错误
        }
    }
    
    /**
     * 解析类级别的OpenAPI属性
     */
    private function parseClassAttributes(\ReflectionClass $reflection, &$openApiData){
        $attributes = $reflection->getAttributes();
        
        foreach ($attributes as $attribute) {
            $name = $attribute->getName();
            $args = $attribute->getArguments();
            
            if ($name === 'OpenApi\\Attributes\\Info') {
                if (isset($args['title'])) {
                    $openApiData['info']['title'] = $args['title'];
                }
                if (isset($args['version'])) {
                    $openApiData['info']['version'] = $args['version'];
                }
                if (isset($args['description'])) {
                    $openApiData['info']['description'] = $args['description'];
                }
            }
        }
    }
    
    /**
     * 解析方法级别的OpenAPI属性
     */
    private function parseMethodAttributes(\ReflectionMethod $method, &$openApiData){
        $attributes = $method->getAttributes();
        
        foreach ($attributes as $attribute) {
            $name = $attribute->getName();
            $args = $attribute->getArguments();
            
            if (in_array($name, [
                'OpenApi\\Attributes\\Get',
                'OpenApi\\Attributes\\Post',
                'OpenApi\\Attributes\\Put',
                'OpenApi\\Attributes\\Delete',
                'OpenApi\\Attributes\\Patch'
            ])) {
                $httpMethod = strtolower(basename($name));
                $path = $args['path'] ?? '';
                
                if ($path) {
                    if (!isset($openApiData['paths'][$path])) {
                        $openApiData['paths'][$path] = [];
                    }
                    
                    $operation = [
                        'summary' => $args['summary'] ?? $method->getName(),
                        'description' => $args['description'] ?? '',
                        'responses' => []
                    ];
                    
                    // 解析responses
                    if (isset($args['responses'])) {
                        foreach ($args['responses'] as $response) {
                            if (is_object($response)) {
                                $responseCode = $response->response ?? '200';
                                $operation['responses'][$responseCode] = [
                                    'description' => $response->description ?? 'Success'
                                ];
                            }
                        }
                    }
                    
                    // 解析parameters
                    if (isset($args['parameters'])) {
                        $operation['parameters'] = [];
                        foreach ($args['parameters'] as $param) {
                            if (is_object($param)) {
                                $operation['parameters'][] = [
                                    'name' => $param->name ?? '',
                                    'in' => $param->in ?? 'query',
                                    'description' => $param->description ?? '',
                                    'required' => $param->required ?? false
                                ];
                            }
                        }
                    }
                    
                    $openApiData['paths'][$path][$httpMethod] = $operation;
                }
            }
        }
    }
}