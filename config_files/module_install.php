<?php

require '/var/www/html/config/config.inc.php';
if (!defined('_PS_VERSION_')) {
    exit(1);
}

if (file_exists(_PS_ROOT_DIR_.'/images.inc.php')) {
    require_once _PS_ROOT_DIR_.'/images.inc.php';
}
else {
    printf("warning: 'images.inc.php' doesn't exist\n");
}


define('_GIT_ENDPOINT_', 'github.com');
define('_GIT_CLONE_', 'git clone --recursive "https://%s/%s" %s'); // args: endpoint, user, pass, repo, name
define('_GIT_CLONE_AUTH_', 'git clone --recursive "https://%s:%s@%s/%s" %s'); // args: user, pass, endpoint, repo, name
define('_GIT_CLONE_SSH_', 'git clone --recursive "git@%s:%s" %s'); // args: endpoint, repo, repo_name
define('_GIT_PULL_', 'git -C "%s" pull -v origin pull/%d/head'); // args: repo_path, pull_id

function handleErrorMuteExpected($severity, $message, $errfile, $errline, $errcontext)
{
    // add the SMARTY_DIR to the list of muted directories
    $_is_muted_directory = false;
    if (!isset(Smarty::$_muted_directories[SMARTY_DIR])) {

        $smarty_dir = realpath(SMARTY_DIR);
        Smarty::$_muted_directories[SMARTY_DIR] = array(
        'file' => $smarty_dir,
        'length' => strlen($smarty_dir),
        );
    }

    // walk the muted directories and test against $errfile

    foreach (Smarty::$_muted_directories as $key => &$dir) {

        if (!$dir) {

            // resolve directory and length for speedy comparisons
            $file = realpath($key);
            $dir = array(
                'file' => $file,
                'length' => strlen($file),
            );
        }

        if (!strncmp($errfile, $dir['file'], $dir['length'])) {
            $_is_muted_directory = true;
            break;
        }
    }

    // generate exeception
    if (!$_is_muted_directory || ($errno && $errno & error_reporting())) {
        throw new PrestaShopException(sprintf("[%s] %s", $severity, $message));
    }
}

function setContext()
{
    global $smarty;

    $context = Context::getContext();

    // Clean all cache values
    Cache::clean('*');

    Context::getContext()->shop = new Shop(1);
    Shop::setContext(Shop::CONTEXT_SHOP, 1);
    Configuration::loadConfiguration();

    if (!isset(Context::getContext()->language) || !Validate::isLoadedObject(Context::getContext()->language)) {
        if ($id_lang = (int)Configuration::get('PS_LANG_DEFAULT')) {
            Context::getContext()->language = new Language($id_lang);
        }
    }

    if (!isset(Context::getContext()->country) || !Validate::isLoadedObject(Context::getContext()->country)) {
        if ($id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT')) {
            Context::getContext()->country = new Country((int)$id_country);
        }
    }

    if (!isset(Context::getContext()->currency) || !Validate::isLoadedObject(Context::getContext()->currency)) {
        if ($id_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT')) {
            Context::getContext()->currency = new Currency((int)$id_currency);
        }
    }

    Context::getContext()->cart = new Cart();

    $cookie =& $context->cookie;

    if (($employee = Db::getInstance()->getRow('SELECT * FROM ps_employee WHERE id_profile='._PS_ADMIN_PROFILE_)) !== false) {

        $cookie->__set('id_employee', $employee['id_employee']);
        $cookie->__set('profile', $employee['id_profile']);
        $cookie->__set('email', $employee['email']);
        $cookie->__set('passwd', $employee['passwd']);
    }

    $employee = new Employee($cookie->id_employee);
    $context->employee = $employee;

    if (!defined('_PS_SMARTY_FAST_LOAD_')) {
        define('_PS_SMARTY_FAST_LOAD_', true);
    }

    require_once _PS_ROOT_DIR_.'/config/smarty.config.inc.php';

    Context::getContext()->smarty = $smarty;
}

function gitPull($repo, $name, $pull = null)
{
    if (!empty($pull)) {         // fetch pull request
        shell_exec(sprintf(_GIT_PULL_, _PS_MODULE_DIR_.$name, $pull));
    }

    return true;
}

function gitClone($repo, $name, array $creds = array (), $pull = null, $endpoint = _GIT_ENDPOINT_)
{
    if (file_exists(_PS_MODULE_DIR_.$name)) { // module already exist proced to install
        return true;
    }

    if (!empty($creds['ssh']) && $creds['ssh']) { // clone with ssh in priority
        shell_exec(sprintf(_GIT_CLONE_SSH_, $endpoint, $repo, _PS_MODULE_DIR_.$name));

    } else if (!empty($cred['user']) && !empty($creds['pass'])) { // Auth/pass is not recommended
        shell_exec(sprintf(_GIT_CLONE_AUTH_, (string)$cred['user'], (string)$creds['pass'], $endpoint, $repo, _PS_MODULE_DIR_.$name));

    } else {                        // basic git clone, may fail
        shell_exec(sprintf(_GIT_CLONE_, $endpoint, $repo, _PS_MODULE_DIR_.$name));
    }

    if (!file_exists(_PS_MODULE_DIR_.$name.'/'.$name.'.php')) { // check for success
        return false;
    }

    if (isset($pull)) {
        gitPull($repo, $name, $pull);
    }

    return true;
}

function moduleCheckSyntax($module_name = null, array &$errors = array ())
{
    if (isset($module_name) && file_exists(_PS_MODULE_DIR_.$module_name.'/'.$module_name.'.php')) {

        $directory = new RecursiveDirectoryIterator(_PS_MODULE_DIR_.$module_name);
        $iterator = new RecursiveIteratorIterator($directory);
        $php_files = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        ob_start();

        foreach ($php_files as $php_file) {
            if (($error = shell_exec(sprintf("php -l %s 2>&1 1>/dev/null", $php_file[0]))) && !empty($error)) {
                $errors[] = sprintf("[%s] %s", basename($php_file[0]), str_replace(PHP_EOL, ' ', $error));
            }
        }

        ob_end_clean();

        if (empty($errors)) {
            return true;
        }

    } else {
        $errors[] = sprintf('the module "%s" does not exist', $module_name);
    }

    return false;
}

function modulesOperations($module_name = null, $method = 'install', array &$errors = array ())
{
    ob_start();

    if (isset($module_name) && file_exists(_PS_MODULE_DIR_.$module_name.'/'.$module_name.'.php')) {
        $module = Module::getInstanceByname($module_name);
        if ($module && method_exists($module, $method)) {

            set_error_handler('handleErrorMuteExpected'); // handle errors

            try {

                if (!$module->{$method}()) {
                    if ($module->getErrors()) {
                        $errors = array_merge($errors, $module->getErrors());
                    } else {
                        $errors[] = sprintf('Cannot %s module "%s"', $method, $module_name);
                    }

                }
                if (!empty($module->warnings)) {
                    $errors = array_merge($errors, $module->getErrors());
                }

            } catch (PrestaShopDatabaseException $e) {

                $errors[] = $e->getMessage();

            } catch (PrestaShopException $e) {
                $errors[] = $e->getMessage();
            }

            restore_error_handler();
        }

    } else {
        $errors[] = sprintf('the module "%s" does not exist', $module_name);
    }

    ob_end_clean();

    if (!empty($errors)) {
        return false;
    }

    return true;
}

function main($modules, $operations, array $git_creds = array())
{
    printf("-- Just let you know which modules are compiled and loaded in the PHP interpreter:\n");
    foreach (get_loaded_extensions() as $module){
        printf("- %s\n", $module);
    }
    printf("-- end of the list --\n\n");

    $re_git = '/(?<name>^\w+$)|(?:^|.+?)\/?(?<repo>\w+\/(?<repo_name>\w+))(?:\/(?:pull\/|)#?(?<pull>\d+)|)$/i';

    foreach ($modules as $module) {

        $errors = array ();
        if (preg_match($re_git, $module, $ret) !== false) {

            if (!empty($ret['name'])) {
                $module_name = $ret['name'];

            } else if (!empty($ret['repo']) && !empty($ret['repo_name'])) {

                $module_name = $ret['repo_name'];
                $ret['pull'] = (isset($ret['pull']) ? $ret['pull'] : false);
                if (isset($git_creds) && !gitClone($ret['repo'], $ret['repo_name'], $git_creds, $ret['pull'])) {
                    continue;
                }
            }

            if (empty($module_name)) {
                continue;
            }

        } else {
            continue;
        }

        printf("[%s] -- First let's check module syntax... ", $module_name);

        if (!moduleCheckSyntax($module_name, $errors)) { // Check module syntax

            printf("FAIL... Dude do you even code... \n\n");

            $operations = array (); // if fail abort operations

        } else {                // else continue

            printf("DONE\n\n");

            printf("[%s] -- Starting module operations:\n", $module_name);
            foreach ($operations as $operation) { // execute operations

                printf("[%s] - %s... ", $module_name, $operation);

                if (!empty($module_name) && modulesOperations($module_name, $operation, $errors)) { // Test operations
                    printf("DONE\n", $operation);

                } else {            // handle errors

                    if (!empty($context->controller->errors)) {

                        $context = Context::getContext();
                        $errors = array_merge($errors, $context->controller->errors);
                        $context->controller->errors = array ();
                    }
                    printf("FAIL\n", $operation);
                }

                if ($operation === 'install') {
                    Module::updateTranslationsAfterInstall(true);
                    Language::updateModulesTranslations(array ($module_name));
                }
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $err) {
                printf("ERROR: [%s]\n", $err);
            }
            return false;
        }

        printf("%s\n", str_repeat('-', 31));
    }

    return true;
}

// TODO: add endpoint
function parseOpt()
{

    $args = array (                // default opts
        'modules' => array (),
        'operations' => array (),
        'git_cred' => array (
            'user' => null,
            'pass' => null,
            'ssh' => null
        )
    );

    $opts = getopt('o:s:p:u:m:');

    if (isset($opts['m'])) {
        $opts['m'] = preg_replace('`http(s)://`i', '', $opts['m']);
        $args['modules'] = explode(':', $opts['m']);
    }

    if (isset($opts['s'])) {
        $args['git_cred']['ssh'] = !!$opts['s'];
    }

    if (isset($opts['o'])) {
        $args['operations'] = explode(':', $opts['o']);
    }

    if (isset($opts['u']) && !empty($opts['u'])) {
        list($args['git_cred']['user'], $args['git_cred']['pass']) = explode(':', $opts['u']);
    }

    return $args;
}

setContext();

$options = parseOpt();
call_user_func_array('main', array_values($options));
