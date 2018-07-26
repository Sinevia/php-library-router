<?php

namespace Plugins;

class Router {

    /**
     * @var array
     */
    private $actions = null;

    /**
     * @var type 
     */
    private $databaseTable = 'snv_actions_action';

    /**
     * @var \Sinevia\SqlDb 
     */
    private $pdo = null;

    /**
     * @var string
     */
    private $uri = null;

    /**
     * @param \Sinevia\SqlDb $pdo
     * @return $this
     */
    public function setDatabase($pdo) {
        $this->pdo = $pdo;
        return $this;
    }

    public function setDatabaseTableName($tableName) {
        $this->databaseTable = $tableName;
        return $this;
    }

    /**
     * @return \Sinevia\SqlDb $pdo
     */
    public function getActions() {
        // Already populated? Yes => return straight away
        if (is_null($this->actions) == false) {
            return $this->actions;
        }
        
        // DEBUG: $this->getDatabase()->debug = true;
        
        $actions = $this->getDatabase()
                ->table($this->databaseTable)
                ->orderBy('ActionName', 'DESC')
                ->select();

        $this->actions = is_array($actions) ? $actions : [];
        
        return $this->actions;
    }

    /**
     * @return \Sinevia\SqlDb $pdo
     */
    public function getDatabase() {
        return $this->pdo;
    }

    /**
     * Returns the URI to the current script
     * @return string
     */
    function getUri() {
        if ($this->uri !== null) {
            return $this->uri;
        }
        if (isset($_SERVER['PATH_INFO'])) {
            return $_SERVER['PATH_INFO'];
        }

        // 1. Uri
        $uri = $_SERVER['REQUEST_URI'];

        // 2. Remove normal query string
        $uri = (strpos($uri, '?') !== false) ? substr($uri, 0, strpos($uri, '?')) : $uri;
        if (stripos($uri, '/' . $_SERVER['SCRIPT_NAME']) === false) {
            return '/';
        }

        // Does URI start with dir name?
        $dir_name = dirname($_SERVER['SCRIPT_NAME']);
        $uri = (stripos($uri, str_replace(DIRECTORY_SEPARATOR, "/", $dir_name), 0) === 0) ? substr($uri, strlen($dir_name)) : $uri;
        return $uri;
    }

    function setUri($uri) {
        $this->uri = $uri;
        return $this;
    }

    public function findAction($actionName) {
        $actions = $this->getActions();

        // 1. Is it direct match?
        $actionNames = array_column($actions, 'ActionName');

        if (in_array($actionName, $actionNames)) {
            $key = array_search($actionName, $actionNames);
            return $actions[$key];
        }

        // 2. Is it pattern match?
        $patterns = array(
            ':any' => '([^/]+)',
            ':num' => '([0-9]+)',
            ':all' => '(.*)',
            ':string' => '([a-zA-Z]+)',
            ':number' => '([0-9]+)',
            ':numeric' => '([0-9-.]+)',
            ':alpha' => '([a-zA-Z0-9-_]+)',
        );

        // DEBUG: var_dump($actionName);

        $actionsWithPattern = array_filter($actions, function($action) {
            return strpos($action['ActionName'], ':') !== false;
        });

        $actionNamesWithPatterns = array_column($actionsWithPattern, 'ActionName');

        foreach ($actionNamesWithPatterns as $actionIndex => $actionNameWithPattern) {
            $alias = strtr($actionNameWithPattern, $patterns);
            $regex = '/^' . str_replace('/', '\/', $alias) . '$/';
            //$regex = '/^\/hello\/(.*)$/';
            $string = '/' . ltrim($actionName, '/');
            // DEBUG: echo '<br />Regex: ';
            // DEBUG: var_dump($regex);
            // DEBUG: echo '<br />String: ';
            // DEBUG: var_dump($string);

            if ($result = preg_match($regex, $actionName, $matched)) {
                // DEBUG: echo '<pre>';var_dump($matched);echo '</pre>';
                $actionWithPattern = $actionsWithPattern[$actionIndex];
                array_shift($matched);
                $actionWithPattern['Parameters'] = $matched;
                return $actionWithPattern;
            } else {
                // DEBUG: echo '<br />Match: ';
                // DEBUG: var_dump("NO MATCH");
            }
            // DEBUG: var_dump($result);
        }

        return null;
    }

    public function install() {
        $this->getDatabase()->table($this->databaseTable)
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

    public function executeFunction($functionName, $arguments = []) {
        if (function_exists($functionName) == false) {
            $statusMessage = 'Function Not Found: ' . $functionName;
            return $statusMessage;
        }
        return call_user_func_array($functionName, $arguments);
    }

    public function executeMethod($className, $methodName, $arguments = []) {
        //require_once PHP_DIR . str_replace('\\', '/', $className) . '.php';

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

        return $refl->invokeArgs(new $className, $arguments);
    }

    public function run($actionName = null) {
        if ($actionName == null) {
            $actionName = $this->getUri();
        }

        $action = self::findAction($actionName);

        if ($action == null) {
            return ('Wrong route, mate! ' . date('Y-m-d H:i:s') . ' - ' . $actionName);
        }

        $chain = array_filter(explode(',', $action['Middleware']));
        $chain[] = $action['Response'];
        $parameters = isset($action['Parameters']) ? $action['Parameters'] : [];

        foreach ($chain as $key => $entry) {
            $chain[$key] = explode('@', $entry);
        }

        foreach ($chain as $command) {
            if (isset($command[1])) {
                $className = $command[0];
                $methodName = $command[1];
                $response = $this->executeMethod($className, $methodName, $parameters);
            } else {
                $functionName = $command[0];
                $response = $this->executeFunction($functionName, $parameters);
            }

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
