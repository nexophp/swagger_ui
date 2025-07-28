<?php 
namespace modules\swagger_ui\lib;

use OpenApi\Annotations as OA;

class OpenApiMdParser
{
    /**
     * 解析Markdown文件为OpenAPI格式
     */
    public static function parseMdToOpenApi(string $mdPath): array
    {
        if (!file_exists($mdPath)) {
            return [];
        }
        
        $content = file_get_contents($mdPath);
        $openApiData = [
            'openapi' => '3.0.0',
            'info' => self::parseInfo($content),
            'paths' => self::parsePaths($content)
        ];
        
        return array_filter($openApiData);
    }

    /**
     * 解析文档信息
     */
    private static function parseInfo(string $content): array
    {
        $info = [
            'title' => 'API Documentation',
            'version' => '1.0.0'
        ];
        
        // 解析标题
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            $info['title'] = trim($matches[1]);
        }
        
        // 解析OpenAPI Info注解
        if (preg_match('/#\s*@OA\\\\Info\(([^)]+)\)/s', $content, $matches)) {
            $infoStr = $matches[1];
            
            if (preg_match('/title\s*=\s*["\']([^"\'\']+)["\']/', $infoStr, $titleMatch)) {
                $info['title'] = $titleMatch[1];
            }
            
            if (preg_match('/version\s*=\s*["\']([^"\'\']+)["\']/', $infoStr, $versionMatch)) {
                $info['version'] = $versionMatch[1];
            }
            
            if (preg_match('/description\s*=\s*["\']([^"\'\']+)["\']/', $infoStr, $descMatch)) {
                $info['description'] = $descMatch[1];
            }
        }
        
        return $info;
    }

    /**
     * 解析API路径
     */
    private static function parsePaths(string $content): array
    {
        $paths = [];
        
        // 解析代码块中的API定义
        preg_match_all('/```php\s*\n([\s\S]*?)\n```/m', $content, $codeBlocks);
        
        foreach ($codeBlocks[1] as $code) {
            $pathData = self::parseCodeBlock($code);
            if ($pathData) {
                $paths = array_merge_recursive($paths, $pathData);
            }
        }
        
        // 解析简单的URL列表格式
        preg_match_all('/^\s*([a-zA-Z]+):\s*(.+)$/m', $content, $urlMatches, PREG_SET_ORDER);
        
        foreach ($urlMatches as $match) {
            $method = strtolower(trim($match[1]));
            $url = trim($match[2]);
            
            // 提取路径
            if (preg_match('/\/([a-zA-Z0-9\/_-]+)/', $url, $pathMatch)) {
                $path = $pathMatch[1];
                if (!str_starts_with($path, '/')) {
                    $path = '/' . $path;
                }
                
                $paths[$path][$method] = [
                    'summary' => ucfirst($method) . ' ' . $path,
                    'responses' => [
                        '200' => ['description' => '成功']
                    ]
                ];
            }
        }
        
        return $paths;
    }

    /**
     * 解析代码块
     */
    private static function parseCodeBlock(string $code): array
    {
        $paths = [];
        
        // 解析OpenAPI注解
        if (preg_match('/#\[OA\\\\(Get|Post|Put|Delete|Patch)\(([^)]+)\)\]/s', $code, $matches)) {
            $method = strtolower($matches[1]);
            $params = $matches[2];
            
            if (preg_match('/path\s*:\s*["\']([^"\'\']+)["\']/', $params, $pathMatch)) {
                $path = $pathMatch[1];
                
                $operation = [
                    'summary' => ucfirst($method) . ' ' . $path,
                    'responses' => self::parseResponses($params)
                ];
                
                $paths[$path][$method] = $operation;
            }
        }
        
        return $paths;
    }

    /**
     * 解析响应
     */
    private static function parseResponses(string $content): array
    {
        $responses = [];
        
        // 解析Response注解
        preg_match_all('/new\s+OA\\\\Response\(\s*response\s*:\s*(\d+)\s*,\s*description\s*:\s*["\']([^"\'\']+)["\']\s*\)/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $responses[$match[1]] = [
                'description' => $match[2]
            ];
        }
        
        // 如果没有找到响应，添加默认响应
        if (empty($responses)) {
            $responses['200'] = ['description' => '成功'];
        }
        
        return $responses;
    }
    
    /**
     * 扫描所有API文档文件
     */
    public static function scanAllApiDocs(): array
    {
        $allPaths = [];
        
        // 扫描modules目录
        $modulesPath = WWW_PATH . '/modules';
        if (is_dir($modulesPath)) {
            $modules = scandir($modulesPath);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..') continue;
                
                $apiPath = $modulesPath . '/' . $module . '/api';
                if (is_dir($apiPath)) {
                    $mdFiles = glob($apiPath . '/*.md');
                    foreach ($mdFiles as $mdFile) {
                        $pathData = self::parseMdToOpenApi($mdFile);
                        if (isset($pathData['paths'])) {
                            $allPaths = array_merge_recursive($allPaths, $pathData['paths']);
                        }
                    }
                }
            }
        }
        
        // 扫描app目录
        $appPath = WWW_PATH . '/app';
        if (is_dir($appPath)) {
            $apps = scandir($appPath);
            foreach ($apps as $app) {
                if ($app === '.' || $app === '..') continue;
                
                $apiPath = $appPath . '/' . $app . '/api';
                if (is_dir($apiPath)) {
                    $mdFiles = glob($apiPath . '/*.md');
                    foreach ($mdFiles as $mdFile) {
                        $pathData = self::parseMdToOpenApi($mdFile);
                        if (isset($pathData['paths'])) {
                            $allPaths = array_merge_recursive($allPaths, $pathData['paths']);
                        }
                    }
                }
            }
        }
        
        return $allPaths;
    }
}