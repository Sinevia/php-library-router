<?php

namespace Plugins;

class RouterPlugin {

    public static function findAction($actionName) {
        $actionName = '/' . trim(str_replace('public/', '', $actionName), '/');
        return db()->table('snv_actions_action')
                        ->where('Action', '=', $actionName)
                        ->selectOne('Middleware,Response');
    }

    public static function install() {
        db()->table('snv_actions_action')
                ->column('Id', 'BIGINT')
                ->column('Status', 'STRING')
                ->column('ActionName', 'STRING')
                ->column('Middleware', 'STRING')
                ->column('Response', 'STRING')
                ->column('Test', 'STRING')
                ->column('CreatedAt', 'DATETIME')
                ->column('UpdatedAt', 'DATETIME')
                ->create();
    }

    public static function run($actionName) {
        $action = self::findAction($actionName);

        if ($action == null) {
            return ('Wrong route, mate! ' . date('Y-m-d H:i:s') . ' - ' . $actionName);
        }

        $chain = array_filter(explode(',', $action['Middleware']));
        $chain[] = $action['Response'];

        foreach ($chain as $key => $entry) {
            $chain[$key] = explode('@', $entry);
        }

        foreach ($chain as $command) {
            $className = $command[0];
            $methodName = $command[1];
            require_once PHP_DIR . str_replace('\\', '/', $className) . '.php';
            if (class_exists($className) == false) {
                $statusMessage = 'Class Not Found: ' . $className;
                return $statusMessage;
            }

            if (method_exists($className, $methodName) == false) {
                $statusMessage = 'Method Not Found: ' . $methodName;
                return $statusMessage;
            }

            $refl = new \ReflectionMethod($className, $methodName);
            if ($refl->isPublic() == false) {
                $statusMessage = 'Method Not Public: ' . $methodName;
                return $statusMessage;
            }

            $class = new $className;
            $response = $class->$methodName();
            
            if ($response == null) {
                continue;
            }
            
            if (is_string($response)) {
                return $response;
            }
            
            return $response->toJson();
        }
    }

}
