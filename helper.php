<?php
/**
 * DokuWiki Plugin farmer (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <grosse@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_farmer extends DokuWiki_Plugin {

    private $allPlugins = array();

    /**
     * Copy a file, or recursively copy a folder and its contents. Adapted for DokuWiki.
     *
     * @todo: needs tests
     *
     * @author      Aidan Lister <aidan@php.net>
     * @author      Michael Große <grosse@cosmocode.de>
     * @version     1.0.1
     * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
     *
     * @param       string $source Source path
     * @param       string $destination      Destination path
     *
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    function io_copyDir($source, $destination) {
        if (is_link($source)) {
            io_lock($destination);
            $result=symlink(readlink($source), $destination);
            io_unlock($destination);
            return $result;
        }

        if (is_file($source)) {
            io_lock($destination);
            $result=copy($source, $destination);
            io_unlock($destination);
            return $result;
        }

        if (!is_dir($destination)) {
            io_mkdir_p($destination);
        }

        $dir = dir($source);
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // recurse into directories
            $this->io_copyDir("$source/$entry", "$destination/$entry");
        }

        $dir->close();
        return true;
    }



    public function getAllPlugins() {
        $dir = dir(DOKU_PLUGIN);
        $plugins = array();
        while (false !== ($entry = $dir->read())) {
            if($entry == '.' || $entry == '..' || $entry == 'testing') {
                continue;
            }
            if (!is_dir(DOKU_PLUGIN ."/$entry")) {
                continue;
            }
            $plugins[] = $entry;
        }
        sort($plugins);
        return $plugins;
    }

    public function getAllAnimals() {
        $animals = array();

        $dir = dir(DOKU_FARMDIR);
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..' || $entry == '_animal') {
                continue;
            }
            $animals[] = $entry;
        }
        $dir->close();
        return $animals;
    }

    public function activatePlugin($plugin, $animal) {
        if (isset($this->allPlugins[$animal])) {
            $plugins = $this->allPlugins[$animal];
        } else {
            include(DOKU_FARMDIR . $animal . '/conf/plugins.local.php');
        }
        if (isset($plugins[$plugin]) && $plugins[$plugin] === 0) {
            unset($plugins[$plugin]);
            $this->writePluginConf($plugins, $animal);
        }
        $this->allPlugins[$animal] = $plugins;
    }

    public function deactivatePlugin($plugin, $animal) {
        if (isset($this->allPlugins[$animal])) {
            $plugins = $this->allPlugins[$animal];
        } else {
            include(DOKU_FARMDIR . $animal . '/conf/plugins.local.php');
        }
        if (!isset($plugins[$plugin]) || $plugins[$plugin] !== 0) {
            $plugins[$plugin] = 0;
            $this->writePluginConf($plugins, $animal);
        }
        $this->allPlugins[$animal] = $plugins;
    }

    public function writePluginConf($plugins, $animal) {
        dbglog($plugins);
        $pluginConf = '<?php' . "\n";
        foreach ($plugins as $plugin => $status) {
            $pluginConf .= '$plugins["' . $plugin  . '"] = ' . $status . ";\n";
        }
        io_saveFile(DOKU_FARMDIR . $animal . '/conf/plugins.local.php', $pluginConf);
        touch(DOKU_FARMDIR . $animal . '/conf/local.php');
    }

    public function addErrorsToForm(\dokuwiki\Form\Form &$form, $errorArray) {
        for ($position = 0; $position < $form->elementCount(); ++$position) {
            if ($form->getElementAt($position) instanceof dokuwiki\Form\TagCloseElement) {
                continue;
            }
            if ($form->getElementAt($position)->attr('name') == '') continue;
            $elementName = $form->getElementAt($position)->attr('name');
            if (!isset($errorArray[$elementName])) continue;
            $form->getElementAt($position)->addClass('error');
            $form->addTagOpen('div',$position+1)->addClass('error');
            $form->addHTML($errorArray[$elementName],$position+2);
            $form->addTagClose('div',$position+3);
        }
    }

    public function reloadAdminPage($page = null) {
        global $ID;
        $get = $_GET;
        if(isset($get['id'])) unset($get['id']);
        if ($page !== null ) {
            $get['page'] = $page;
        }
        $self = wl($ID, $get, false, '&');
        send_redirect($self);
    }

    public function downloadTemplate($animalpath) {
        file_put_contents($animalpath . '/_animal.zip',fopen('https://www.dokuwiki.org/_media/dokuwiki_farm_animal.zip','r'));
        $zip = new ZipArchive();
        $zip->open($animalpath.'/_animal.zip');
        $zip->extractTo($animalpath);
        $zip->close();
        unlink($animalpath.'/_animal.zip');
    }

    /**
     * recursive function to test wether a (non-existing) path points into an existint path
     *
     * @param $path string
     *
     * @param $container string has to exist
     *
     * @throws BadMethodCallException
     *
     * @return bool
     */
    public function isInPath ($path, $container) {
        if (!file_exists($container)) {
            throw new BadMethodCallException('The Container has to exist and be accessable by realpath().');
        }
        if (realpath($path) === false) {
            return $this->isInPath(dirname($path), $container);
        }
        if (strpos(realpath($path), realpath($container)) !== false) {
            return true;
        } else {
            return false;
        }
    }

}
