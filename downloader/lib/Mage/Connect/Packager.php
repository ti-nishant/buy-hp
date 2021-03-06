<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Connect
 * @copyright   Copyright (c) 2011 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class to manipulate with packages
 *
 * @category    Mage
 * @package     Mage_Connect
 * @author      Magento Core Team <core@magentocommerce.com>
 */

class Mage_Connect_Packager
{
    const CONFIG_FILE_NAME='downloader/connect.cfg';
    const CACHE_FILE_NAME='downloader/cache.cfg';

    protected  $install_states = array(
                    'install' => 'Ready to install',
                    'upgrade' => 'Ready to upgrade',
                    'already_installed' => 'Already installed',
                    'wrong_version' => 'Wrong version',
                );

    /**
     * Constructor
     * @param Mage_connect_Config $config
     */
    public function __construct()
    {

    }

    /**
     *
     * @var Mage_Archive
     */
    protected $_archiver = null;
    protected $_http = null;



    /**
     *
     * @return Mage_Archive
     */
    public function getArchiver()
    {
        if(is_null($this->_archiver)) {
            $this->_archiver = new Mage_Archive();
        }
        return $this->_archiver;
    }

    public function getDownloader()
    {
        if(is_null($this->_http)) {
            $this->_http = Mage_HTTP_Client::getInstance();
        }
        return $this->_http;
    }


    public function getRemoteConf($ftpString)
    {
        $ftpObj = new Mage_Connect_Ftp();
        $ftpObj->connect($ftpString);
        $cfgFile = self::CONFIG_FILE_NAME;
        $cacheFile = self::CACHE_FILE_NAME;


        $wd = $ftpObj->getcwd();

        $remoteConfigExists = $ftpObj->fileExists($cfgFile);
        $tempConfigFile = tempnam(sys_get_temp_dir(),'conf');
        if(!$remoteConfigExists) {
            $remoteCfg = new Mage_Connect_Config($tempConfigFile);
            $remoteCfg->store();
            $ftpObj->upload($cfgFile, $tempConfigFile);
        } else {
            $ftpObj->get($tempConfigFile, $cfgFile);
            $remoteCfg = new Mage_Connect_Config($tempConfigFile);
        }

        $ftpObj->chdir($wd);

        $remoteCacheExists = $ftpObj->fileExists($cacheFile);
        $tempCacheFile = tempnam(sys_get_temp_dir(),'cache');

        if(!$remoteCacheExists) {
            $remoteCache = new Mage_Connect_Singleconfig($tempCacheFile);
            $remoteCache->clear();
            $ftpObj->upload($cacheFile, $tempCacheFile);
        } else {
            $ftpObj->get($tempCacheFile, $cacheFile);
            $remoteCache = new Mage_Connect_Singleconfig($tempCacheFile);
        }
        $ftpObj->chdir($wd);
        return array($remoteCache, $remoteCfg, $ftpObj);
    }


    public function getRemoteCache($ftpString)
    {

        $ftpObj = new Mage_Connect_Ftp();
        $ftpObj->connect($ftpString);
        $remoteConfigExists = $ftpObj->fileExists(self::CACHE_FILE_NAME);
        if(!$remoteConfigExists) {
            $configFile=tempnam(sys_get_temp_dir(),'conf');
            $remoteCfg = new Mage_Connect_Singleconfig($configFile);
            $remoteCfg->clear();
            $ftpObj->upload(self::CACHE_FILE_NAME, $configFile);
        } else {
            $configFile=tempnam(sys_get_temp_dir(),'conf');
            $ftpObj->get($configFile, self::CACHE_FILE_NAME);
            $remoteCfg = new Mage_Connect_Singleconfig($configFile);
        }
        return array($remoteCfg, $ftpObj);
    }


    public function getRemoteConfig($ftpString)
    {
        $ftpObj = new Mage_Connect_Ftp();
        $ftpObj->connect($ftpString);
        $cfgFile = self::CONFIG_FILE_NAME;

        $wd = $ftpObj->getcwd();
        $remoteConfigExists = $ftpObj->fileExists($cfgFile);
        $tempConfigFile = tempnam(sys_get_temp_dir(),'conf_');
        if(!$remoteConfigExists) {
            $remoteCfg = new Mage_Connect_Config($tempConfigFile);
            $remoteCfg->store();
            $ftpObj->upload($cfgFile, $tempConfigFile);
        } else {
            $ftpObj->get($tempConfigFile, $cfgFile);
            $remoteCfg = new Mage_Connect_Config($tempConfigFile);
        }
        $ftpObj->chdir($wd);
        return array($remoteCfg, $ftpObj);
    }

    public function writeToRemoteCache($cache, $ftpObj)
    {
        $wd = $ftpObj->getcwd();
        $ftpObj->upload(self::CACHE_FILE_NAME, $cache->getFilename());
        @unlink($cache->getFilename());
        $ftpObj->chdir($wd);
    }

    public function writeToRemoteConfig($cache, $ftpObj)
    {
        $wd = $ftpObj->getcwd();
        $ftpObj->upload(self::CONFIG_FILE_NAME, $cache->getFilename());
        @unlink($cache->getFilename());
        $ftpObj->chdir($wd);
    }

    /**
     * Remove empty directories recursively up
     * @param string $dir
     * @param Mage_Connect_Ftp $ftp
     */
    protected function removeEmptyDirectory($dir, $ftp = null)
    {
        if ($ftp) {
            if (count($ftp->nlist($dir))==0) {
                if ($ftp->rmdir($dir)) {
                    $this->removeEmptyDirectory(dirname($dir), $ftp);
                }
            }
        } else {
            if (@rmdir($dir)) {
                $this->removeEmptyDirectory(dirname($dir), $ftp);
            }
        }
    }

    /**
     *
     * @param $chanName
     * @param $package
     * @param Mage_Connect_Singleconfig $cacheObj
     * @param Mage_Connect_Config $configObj
     * @return unknown_type
     */
    public function processUninstallPackage($chanName, $package, $cacheObj, $configObj)
    {
        $package = $cacheObj->getPackageObject($chanName, $package);
        $contents = $package->getContents();

        $targetPath = rtrim($configObj->magento_root, "\\/");
        foreach($contents as $file) {
            $fileName = basename($file);
            $filePath = dirname($file);
            $dest = $targetPath . DIRECTORY_SEPARATOR . $filePath . DIRECTORY_SEPARATOR . $fileName;
            if(@file_exists($dest)) {
                @unlink($dest);
                $this->removeEmptyDirectory(dirname($dest));
            }
        }

        $destDir = $targetPath . DS . Mage_Connect_Package::PACKAGE_XML_DIR;
        $destFile = $package->getReleaseFilename() . '.xml';
        @unlink($destDir . DS . $destFile);
    }

    /**
     *
     * @param $chanName
     * @param $package
     * @param Mage_Connect_Singleconfig $cacheObj
     * @param Mage_Connect_Ftp $ftp
     * @return unknown_type
     */
    public function processUninstallPackageFtp($chanName, $package, $cacheObj, $ftp)
    {
        $ftpDir = $ftp->getcwd();
        $package = $cacheObj->getPackageObject($chanName, $package);
        $contents = $package->getContents();
        foreach($contents as $file) {
            $res = $ftp->delete($file);
            $this->removeEmptyDirectory(dirname($file), $ftp);
        }
        $remoteXml = Mage_Connect_Package::PACKAGE_XML_DIR . DS . $package->getReleaseFilename() . '.xml';
        $ftp->delete($remoteXml);
        $ftp->chdir($ftpDir);
    }

    /**
     * Validation of mode permissions
     *
     * @param int $mode
     * @return int
     */
    protected function validPermMode($mode)
    {
        $mode = intval($mode);
        if ($mode < 73 || $mode > 511) {
            return false;
        }
        return true;
    }
    /**
     *
     * Return correct global dir mode in octal representation
     *
     * @param Maged_Model_Config $config
     * @return int
     */
    protected function _getDirMode($config)
    {
        if ($this->validPermMode($config->global_dir_mode)) {
            return $config->global_dir_mode;
        } else {
            return $config->getDefaultValue('global_dir_mode');
        }
    }

    /**
     * Return global file mode in octal representation
     *
     * @param Maged_Model_Config $config
     * @return int
     */
    protected function _getFileMode($config)
    {
        if ($this->validPermMode($config->global_file_mode)) {
            return $config->global_file_mode;
        } else {
            return $config->getDefaultValue('global_file_mode');
        }
    }

    /**
     * Convert FTP path
     *
     * @param string $str
     * @return string
     */
    protected function convertFtpPath($str)
    {
        return str_replace("\\", "/", $str);
    }

    public function processInstallPackageFtp($package, $file, $configObj, $ftp)
    {
        $ftpDir = $ftp->getcwd();
        $contents = $package->getContents();
        $arc = $this->getArchiver();
        $target = dirname($file).DS.$package->getReleaseFilename();
        @mkdir($target, 0777, true);
        $tar = $arc->unpack($file, $target);
        $modeFile = $this->_getFileMode($configObj);
        $modeDir = $this->_getDirMode($configObj);
        foreach($contents as $file) {
            $fileName = basename($file);
            $filePath = $this->convertFtpPath(dirname($file));
            $source = $tar.DS.$file;
            if (file_exists($source) && is_file($source)) {
                $args = array(ltrim($file,"/"), $source);
                if($modeDir||$modeFile) {
                    $args[] = $modeDir;
                    $args[] = $modeFile;
                }
                call_user_func_array(array($ftp,'upload'), $args);
            }
        }

        $localXml = $tar . Mage_Connect_Package_Reader::DEFAULT_NAME_PACKAGE;
        if (is_file($localXml)) {
            $remoteXml = Mage_Connect_Package::PACKAGE_XML_DIR . DS . $package->getReleaseFilename() . '.xml';
            $ftp->upload($remoteXml, $localXml, $modeDir, $modeFile);
        }

        $ftp->chdir($ftpDir);
        Mage_System_Dirs::rm(array("-r",$target));
    }

    /**
     * Package installation to FS
     * @param Mage_Connect_Package $package
     * @param string $file
     * @return void
     * @throws Exception
     */
    public function processInstallPackage($package, $file, $configObj)
    {
        $contents = $package->getContents();
        $arc = $this->getArchiver();
        $target = dirname($file).DS.$package->getReleaseFilename();
        @mkdir($target, 0777, true);
        $tar = $arc->unpack($file, $target);
        $modeFile = $this->_getFileMode($configObj);
        $modeDir = $this->_getDirMode($configObj);
        foreach($contents as $file) {
            $fileName = basename($file);
            $filePath = dirname($file);
            $source = $tar.DS.$file;
            $targetPath = rtrim($configObj->magento_root, "\\/");
            @mkdir($targetPath. DS . $filePath, $modeDir, true);
            $dest = $targetPath . DS . $filePath . DS . $fileName;
            if (is_file($source)) {
                @copy($source, $dest);
                if($modeFile) {
                    @chmod($dest, $modeFile);
                }
            } else {
                @mkdir($dest, $modeDir);
            }
        }

        $packageXml = $tar . Mage_Connect_Package_Reader::DEFAULT_NAME_PACKAGE;
        if (is_file($packageXml)) {
            $destDir = $targetPath . DS . Mage_Connect_Package::PACKAGE_XML_DIR;
            $destFile = $package->getReleaseFilename() . '.xml';
            $dest = $destDir . DS . $destFile;

            @copy($packageXml, $dest);
            @chmod($dest, $modeFile);
        }

        Mage_System_Dirs::rm(array("-r",$target));
    }


    /**
     * Get local modified files
     * @param $chanName
     * @param $package
     * @param $cacheObj
     * @param $configObj
     * @return array
     */
    public function getLocalModifiedFiles($chanName, $package, $cacheObj, $configObj)
    {
        $p = $cacheObj->getPackageObject($chanName, $package);
        $hashContents = $p->getHashContents();
        $listModified = array();
        foreach ($hashContents as $file=>$hash) {
            if (md5_file($configObj->magento_root . DS . $file)!==$hash) {
                $listModified[] = $file;
            }
        }
        return $listModified;
    }

    /**
     * Get remote modified files
     *
     * @param $chanName
     * @param $package
     * @param $cacheObj
     * @param Mage_Connect_Ftp $ftp
     * @return array
     */
    public function getRemoteModifiedFiles($chanName, $package, $cacheObj, $ftp)
    {
        $p = $cacheObj->getPackageObject($chanName, $package);
        $hashContents = $p->getHashContents();
        $listModified = array();
        foreach ($hashContents as $file=>$hash) {
            $localFile = uniqid("temp_remote_");
            if(!$ftp->fileExists($file)) {
                continue;
            }
            $ftp->get($localFile, $file);
            if (file_exists($localFile) && md5_file($localFile)!==$hash) {
                $listModified[] = $file;
            }
            @unlink($localFile);
        }
        return $listModified;
    }


    /**
     *
     * Get upgrades list
     *
     * @param string/array $channels
     * @param Mage_Connect_Singleconfig $cacheObject
     * @param Mage_Connect_Rest $restObj optional
     * @param bool $checkConflicts
     * @return array
     */
    public function getUpgradesList($channels, $cacheObject, $configObj, $restObj = null, $checkConflicts = false)
    {
        if(is_scalar($channels)) {
            $channels = array($channels);
        }

        if(!$restObj) {
            $restObj = new Mage_Connect_Rest($configObj->protocol);
        }

        $updates = array();
        foreach($channels as $chan) {

            if(!$cacheObject->isChannel($chan)) {
                continue;
            }
            $chanName = $cacheObject->chanName($chan);
            $localPackages = $cacheObject->getInstalledPackages($chanName);
            $localPackages = $localPackages[$chanName];

            if(!count($localPackages)) {
                continue;
            }

            $channel = $cacheObject->getChannel($chan);
            $uri = $channel[Mage_Connect_Singleconfig::K_URI];
            $restObj->setChannel($uri);
            $remotePackages = $restObj->getPackagesHashed();

            /**
             * Iterate packages of channel $chan
             */
            $state = $configObj->preferred_state ? $configObj->preferred_state : "stable";

            foreach($localPackages as $localName=>$localData) {
                if(!isset($remotePackages[$localName])) {
                    continue;
                }
                $package = $remotePackages[$localName];
                $neededToUpgrade = false;
                $remoteVersion = $localVersion = trim($localData[Mage_Connect_Singleconfig::K_VER]);
                foreach($package as $version => $s) {

                    if( $cacheObject->compareStabilities($s, $state) < 0 ) {
                        continue;
                    }

                    if(version_compare($version, $localVersion, ">")) {
                        $neededToUpgrade = true;
                        $remoteVersion = $version;
                    }

                    if($checkConflicts) {
                        $conflicts = $cacheObject->hasConflicts($chanName, $localName, $remoteVersion);
                        if(false !== $conflicts) {
                            $neededToUpgrade = false;
                        }
                    }
                }
                if(!$neededToUpgrade) {
                    continue;
                }
                if(!isset($updates[$chanName])) {
                    $updates[$chanName] = array();
                }
                $updates[$chanName][$localName] = array("from"=>$localVersion, "to"=>$remoteVersion);
            }
        }
        return $updates;
    }

    /**
     * Get uninstall list
     * @param string $chanName
     * @param string $package
     * @param Mage_Connect_Singleconfig $cache
     * @param Mage_Connect_Config $config
     * @param bool $withDepsRecursive
     * @return array
     */
    public function getUninstallList($chanName, $package, $cache, $config, $withDepsRecursive = true)
    {
        static $level = 0;
        static $hash = array();

        $chanName = $cache->chanName($chanName);
        $keyOuter = $chanName . "/" . $package;
        $level++;

        try {
            $chanName = $cache->chanName($chanName);
            if(!$cache->hasPackage($chanName, $package)) {
                $level--;
                if($level == 0) {
                    $hash = array();
                    return array('list'=>array());
                }
                return;
            }
            $dependencies = $cache->getPackageDependencies($chanName, $package);
            $data = $cache->getPackage($chanName, $package);
            $version = $data['version'];
            $keyOuter = $chanName . "/" . $package;

            //print "Processing outer: {$keyOuter} \n";
            $hash[$keyOuter] = array (
                        'name' => $package,
                        'channel' => $chanName,
                        'version' => $version,
                        'packages' => $dependencies,
            );

            if($withDepsRecursive) {
                $flds = array('name','channel','min','max');
                $fldsCount = count($flds);
                foreach($dependencies as $row) {
                    foreach($flds as $key) {
                        $varName = "p".ucfirst($key);
                        $$varName = $row[$key];
                    }
                    $method = __FUNCTION__;
                    $keyInner = $pChannel . "/" . $pName;
                    if(!isset($hash[$keyInner])) {
                        $this->$method($pChannel, $pName, $cache, $config,
                        $withDepsRecursive, false);
                    }
                }
            }

        } catch (Exception $e) {
//            $this->_failed[] = array(
//                'name'=>$package,
//                'channel'=>$chanName,
//                'max'=>$versionMax,
//                'min'=>$versionMin,
//                'reason'=>$e->getMessage()
//            );
        }

        $level--;
        if(0 === $level) {
            $out = $this->processDepsHash($hash);
            $hash = array();
            return array('list'=>$out);
        }
    }

    /**
     * Add data to package dependencies hash array
     * @param array $hash Package dependencies hash array
     * @param string $name Package name
     * @param string $channel Package chaannel
     * @param string $downloaded_version Package downloaded version
     * @param string $stability Package stability
     * @param string $versionMin Required package minimum version
     * @param string $versionMax Required package maximum version
     * @param string $install_state Package install state
     * @param string $message Package install message
     * @param array $dependencies Package dependencies
     */
    private function addHashData(&$hash, $name, $channel, $downloaded_version = '', $stability = '', $versionMin = '',
            $versionMax = '', $install_state = '', $message = '', $dependencies = '')
    {
            /**
             * @todo When we are building dependencies tree we should base this calculations not on full key as on a
             * unique value but check it by parts. First part which should be checked is EXTENSION_NAME also this
             * part should be unique globally not per channel.
             */
            //$key = $chanName . "/" . $package;
            $key = $name;
            $hash[$key] = array (
                'name' => $name,
                'channel' => $channel,
                'downloaded_version' => $downloaded_version,
                'stability' => $stability,
                'min' => $versionMin,
                'max' => $versionMax,
                'install_state' => $install_state,
                'message' => (isset($this->install_states[$install_state]) ?
                        $this->install_states[$install_state] : '').$message,
                'packages' => $dependencies,
            );

            return true;
    }

    /**
     * Get dependencies list/install order info
     *
     *
     * @param string $chanName
     * @param string $package
     * @param Mage_Connect_Singleconfig $cache
     * @param Mage_Connect_Config $config
     * @param mixed $versionMax
     * @param mixed $versionMin
     * @param boolean $withDepsRecursive
     * @param boolean $forceRemote
     * @param Mage_Connect_Rest $rest
     * @return mixed
     */
    public function getDependenciesList( $chanName, $package, $cache, $config, $versionMax = false, $versionMin = false,
            $withDepsRecursive = true, $forceRemote = false, $rest = null)
    {

        static $level = 0;
        static $_depsHash = array();
        static $_deps = array();
        static $_failed = array();
        $install_state = 'install';
        $version = '';
        $stability = '';
        $message = '';
        $dependencies = array();

        $level++;

        try {
            $chanName = $cache->chanName($chanName);

            if (!$rest){
                $rest = new Mage_Connect_Rest($config->protocol);
            }
            $rest->setChannel($cache->chanUrl($chanName));
            $releases = $rest->getReleases($package);
            if (!$releases || !count($releases)) {
                throw new Exception("No releases for '{$package}', skipping");
            }
            $state = $config->preferred_state ? $config->preferred_state : 'stable';
            /**
             * Check current package version first
             */
            $installedPackage = $cache->getPackage($chanName, $package);
            if ($installedPackage && is_array($installedPackage)) {
                $installedRelease = array(array(
                    'v' => $installedPackage['version'],
                    's' => $installedPackage['stability'],
                ));
                $version = $cache->detectVersionFromRestArray($installedRelease, $versionMin, $versionMax, $state);
            }
            if (!$version) {
                $version = $cache->detectVersionFromRestArray($releases, $versionMin, $versionMax, $state);
            }
            if (!$version) {
                $versionState = $cache->detectVersionFromRestArray($releases, $versionMin, $versionMax);
                if ($versionState) {
                    $packageInfo = $rest->getPackageReleaseInfo($package, $versionState);
                    if (false !== $packageInfo) {
                        $stability = $packageInfo->getStability();
                        throw new Exception("Extension is '{$stability}' please check(or change) stability settings".
                                            " on Magento Connect Manager");
                    }
                }
                throw new Exception("Version for '{$package}' was not detected");
            }
            $packageInfo = $rest->getPackageReleaseInfo($package, $version);
            if (false === $packageInfo) {
                throw new Exception("Package release '{$package}' not found on server");
            }
            $stability = $packageInfo->getStability();

            /**
             * @todo check is package already installed
             */
            if ($installedPackage = $cache->isPackageInstalled($package)) {
                if ($chanName == $installedPackage['channel']){
                    /**
                     * @todo check versions!!!
                     */
                    if (version_compare($version, $installedPackage['version'], '>')) {
                        $install_state = 'upgrade';
                    } elseif (version_compare($version, $installedPackage['version'], '<')) {
                        $version = $installedPackage['version'];
                        $stability = $installedPackage['stability'];
                        $install_state = 'wrong_version';
                    } else {
                        $install_state = 'already_installed';
                    }
                } else {
                    $install_state = 'incompatible';
                }
            }

            $deps_tmp = $packageInfo->getDependencyPackages();

            /**
             * @todo Select distinct packages grouped by name
             */
            $dependencies = array();
            foreach ($deps_tmp as $row) {
                if (isset($dependencies[$row['name']])) {
                    if ($installedPackageDep = $cache->isPackageInstalled($row['name'])) {
                        if ($installedPackageDep['channel'] == $row['channel']) {
                            $dependencies[$row['name']]=$row;
                        }
                    } elseif ($config->root_channel == $row['channel']) {
                        $dependencies[$row['name']] = $row;
                    }
                } else {
                    $dependencies[$row['name']] = $row;
                }
            }

            /**
             * @todo When we are building dependencies tree we should base this calculations not on full key as on a
             * unique value but check it by parts. First part which should be checked is EXTENSION_NAME also this part
             * should be unique globally not per channel.
             */
            // $keyOuter = $chanName . "/" . $package;
            $keyOuter = $package;

            $this->addHashData($_depsHash, $package, $chanName, $version, $stability, $versionMin,
                    $versionMax, $install_state, $message, $dependencies);

            if ($withDepsRecursive && 'incompatible' != $install_state) {
                $flds = array('name','channel','min','max');
                $fldsCount = count($flds);
                foreach($dependencies as $row) {
                    foreach($flds as $key) {
                        $varName = "p".ucfirst($key);
                        $$varName = $row[$key];
                    }
                    $method = __FUNCTION__;
                    /**
                     * @todo When we are building dependencies tree we should base this calculations not on full key as
                     * on a unique value but check it by parts. First part which should be checked is EXTENSION_NAME
                     * also this part should be unique globally not per channel.
                     */
                    //$keyInner = $pChannel . "/" . $pName;
                    $keyInner = $pName;
                    if(!isset($_depsHash[$keyInner])) {
                        $_deps[] = $row;
                        $this->$method($pChannel, $pName, $cache, $config,
                        $pMax, $pMin, $withDepsRecursive, $forceRemote, $rest);
                    } else {
                        $downloaded = $_depsHash[$keyInner]['downloaded_version'];
                        $hasMin = $_depsHash[$keyInner]['min'];
                        $hasMax = $_depsHash[$keyInner]['max'];
                        if($pMin === $hasMin && $pMax === $hasMax) {
                            //var_dump("Equal requirements, skipping");
                            continue;
                        }

                        if($cache->versionInRange($downloaded, $pMin, $pMax)) {
                            //var_dump("Downloaded package matches new range too");
                            continue;
                        }

                        $names = array("pMin","pMax","hasMin","hasMax");
                        for($i=0, $c=count($names); $i<$c; $i++) {
                            if(!isset($$names[$i])) {
                                continue;
                            }
                            if(false !== $$names[$i]) {
                                continue;
                            }
                            $$names[$i] = $i % 2 == 0 ? "0" : "999999999";
                        }

                        if(!$cache->hasVersionRangeIntersect($pMin,$pMax, $hasMin, $hasMax)) {
                            $reason = "Detected {$pName} conflict of versions: {$hasMin}-{$hasMax} and {$pMin}-{$pMax}";
                            unset($_depsHash[$keyInner]);
                            $_failed[] = array(
                                'name'=>$pName,
                                'channel'=>$pChannel,
                                'max'=>$pMax,
                                'min'=>$pMin,
                                'reason'=>$reason
                            );
                            continue;
                        }
                        $newMaxIsLess = version_compare($pMax, $hasMax, "<");
                        $newMinIsGreater = version_compare($pMin, $hasMin, ">");
                        $forceMax = $newMaxIsLess ? $pMax : $hasMax;
                        $forceMin = $newMinIsGreater ? $pMin : $hasMin;
                        //var_dump("Trying to process {$pName} : max {$forceMax} - min {$forceMin}");
                        $this->$method($pChannel, $pName, $cache, $config,
                        $forceMax, $forceMin, $withDepsRecursive, $forceRemote, $rest);
                    }
                }
            }
            unset($rest);
        } catch (Exception $e) {
            $_failed[] = array(
                'name'=>$package,
                'channel'=>$chanName,
                'max'=>$versionMax,
                'min'=>$versionMin,
                'reason'=>$e->getMessage()
            );
        }


        $level--;
        if($level == 0) {
            $out = $this->processDepsHash($_depsHash, false);
            $deps = $_deps;
            $failed = $_failed;
            $_depsHash = array();
            $_deps = array();
            $_failed = array();
            return array('deps' => $deps, 'result' => $out, 'failed'=> $failed);
        }

    }


    /**
     * Process dependencies hash
     * Makes topological sorting and gives operation order list
     *
     * @param array $depsHash
     * @param bool $sortReverse
     * @return array
     */
    protected function processDepsHash(&$depsHash, $sortReverse = true)
    {
        $nodes = array();
        $graph = new Mage_Connect_Structures_Graph();

        foreach($depsHash as $key=>$data) {
            $packages = $data['packages'];
            $node = new Mage_Connect_Structures_Node();
            $nodes[$key] =& $node;
            unset($data['packages']);
            $node->setData($data);
            $graph->addNode($node);
            unset($node);
        }

        if(count($nodes) > 1) {
            foreach($depsHash as $key=>$data) {
                $packages = $data['packages'];
                foreach($packages as $pdata) {
                    $pName = $pdata['name'];
                    if(isset($nodes[$key], $nodes[$pName])) {
                        $nodes[$key]->connectTo($nodes[$pName]);
                    }
                }
            }
        }

        if (!$graph->isAcyclic()) {
            throw new Exception("Dependency references are cyclic");
        }

        $result = $graph->topologicalSort();
        $sortReverse ? krsort($result) : ksort($result);
        $out = array();
        $total = 0;
        foreach($result as $order=>$nodes) {
            foreach($nodes as $n) {
                $out[] = $n->getData();
            }
        }
        unset($graph, $nodes);
        return $out;
    }

}
