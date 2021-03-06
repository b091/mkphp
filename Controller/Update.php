<?php

/**
 * MK_Controller_Update
 *
 * Klasa do obsługi aktualizaji
 *
 * @category    MK_Controller
 * @package     MK_Controller_Update
 * @author    bskrzypkowiak
 */
class MK_Controller_Update
{

    /**
     * Nazwa aplikacji
     *
     * @var string
     */
    private $appName = APP_NAME;

    /**
     * @var bool
     */
    private $superAdmin = false;

    /**
     * RegExp do odczytywania logów z pliku
     *
     * @var string
     */
    private $logRegExp = "#(\d+-\d+-\d+ \d+:\d+:\d+) (\w+\.\w+): (.*)#";

    /**
     * obecna licencja
     *
     * @var String|null
     */
    private $licence = null;

    /**
     * obecna wersja
     *
     * @var String|null
     */
    private $currentVersion = null;

    /**
     * dostepne wersje do upgradu
     *
     * @var String|null
     */
    private $allowVersion = null;

    /**
     * numer najnowszej wersji z rejestru zmian
     *
     * @var String|null
     */
    private $releasedVersion = null;

    /**
     * lista dostęnych zadań
     *
     * @var array
     */
    private $patchTaskList = array (
        'patch' => 'Poprawki',
        'patch_dev' => 'Poprawki niestabilne',
        'patch_rc' => 'Poprawki kandydujące do wersji stabilnej',
        'upgrade' => 'Aktualizacja do wersji '
    );

    /**
     * Konstruktor
     *
     * @throws MK_Exception
     */
    public function __construct()
    {
        if(is_null($this->currentVersion)) {
            throw new MK_Exception('Nieustawione parametry wejściowe dla MK_Controller_Update');
            // Przykładowa konfiguracja dla SPiRB-a
//			$configDb = new ConfigDb();
//			$this
//				->setAppVersion($configDb->getAppVersion()) // SELECT get_app_version();
//				->setAppName(APP_NAME)
//				->setLicense($configDb->getValue('spirb_licence'))
//				->setAllowedVersion($configDb->getValue('allow_version_to_upgrade')) // SELECT config_value FROM swpirb_config WHERE symbol = 'allow_version_to_upgrade';
//				->setReleasedVersion($configDb->getReleasedVersion()) // SELECT subject FROM sys_version ORDER BY id DESC;
//				->setSuperAdmin(MK_IS_CLI === false ? UserSingleton::getInstance()->getCurrentUserInstance()->isSuperAdmin() : false);
//			parent::__construct();
        }

        $this->preparePatchTaskList();

        //@TODO sprawdzanie czy konto jest z uprawnieniami administratora
        if(!file_exists(MTM_FILE_LIST) || !is_writable(MTM_FILE_LIST)) {
            throw new MK_Exception('Problem z zapisem do pliku.');
        }
    }

    /**
     * Ustawia aktualny nr wersji aplikacji
     *
     * @param $v
     *
     * @return MK_Controller_Update
     */
    protected function setAppVersion($v)
    {
        $this->currentVersion = $v;
        return $this;
    }

    /**
     * Ustawia klucz licencji
     *
     * @param $v
     *
     * @return MK_Controller_Update
     */
    protected function setLicense($v)
    {
        $this->licence = $v;
        return $this;
    }

    /**
     * Ustawia nr wersji do której można wykonać aktualizację
     *
     * @param $v
     *
     * @return MK_Controller_Update
     */
    protected function setAllowedVersion($v)
    {
        $this->allowVersion = $v;
        return $this;
    }

    /**
     * Ustawia nr wersji jaka została ostatnio wydana
     *
     * @param $v
     *
     * @return MK_Controller_Update
     */
    protected function setReleasedVersion($v)
    {
        $this->releasedVersion = $v;
        return $this;
    }

    /**
     * Ustawia czy zalogowany użytkownik to superadmin z możliwością wykonania aktualizacji z trunka
     *
     * @param $v
     *
     * @return MK_Controller_Update
     */
    protected function setSuperAdmin($v)
    {
        $this->superAdmin = $v; //UserSingleton::getInstance()->getCurrentUserInstance()->isSuperAdmin();
        return $this;
    }

    /**
     * Ustawia nazwe aplikacji w celu wprowadzania wpisów w mtm
     *
     * @param $v
     *
     * @return MK_Controller_Update
     */
    protected function setAppName($v)
    {
        $this->appName = $v;
        return $this;
    }

    /**
     * Ustawia dane w tabeli przechowującej możliwości do aktualizacji
     *
     * @return array
     */
    public function getPatchTaskList()
    {
        return $this->patchTaskList;
    }

    /**
     * Tworzy i zwraca stora do comboboxa z mozliwosciami aktualizacji
     * W przypadku podania parametru jako true zwróci tablice z opcjami zamiast stora
     *
     * @param $args
     *
     * @return Array
     */
    public function getPatchComboStore($args)
    {
        $store = array ();
        foreach ($this->patchTaskList as $key => $val) {
            $store[] = array ("name" => $key, "description" => $val);
        }
        return $store;
    }

    /**
     * Ustawia dane w tabeli przechowującej możliwości do aktualizacji
     *
     * @return bool
     */
    public function preparePatchTaskList()
    {
        $this->patchTaskList['upgrade'] .= $this->allowVersion;

        $allowVersion = (int)str_replace('.', '', $this->allowVersion);
        $currentVersion = (int)str_replace('.', '', $this->currentVersion);
        $releasedVersion = (int)str_replace('.', '', $this->releasedVersion);

        if(!($currentVersion < $allowVersion)) {
            unset($this->patchTaskList['upgrade']);
        }

        // przeypadek kiedy jest to niewydana wersja, tzn w APP_NAME_conf jest 0.1.2,
        // a w rejestrze zmian jest 0.1.1
        if($currentVersion == $releasedVersion + 1) {
            // patch_rc
            unset($this->patchTaskList['patch_dev']);
            unset($this->patchTaskList['patch']);
        } else {
            // patch | patch_dev
            unset($this->patchTaskList['patch_rc']);
        }

        if(MK_IS_CLI === false && $this->superAdmin === false) {
            unset($this->patchTaskList['patch_dev']);
            unset($this->patchTaskList['patch_rc']);
        }

        return true;
    }

    /**
     * Uruchamia aktualizacje
     *
     * @param array $args
     *
     * @throws MK_Exception
     * @return array
     */
    public function run(array $args)
    {
        $type = isset($args['type']) ? $args['type'] : null;
        $force = isset($args['force']) ? $args['force'] : false;

        if(empty($args['type'])) {
            throw new MK_Exception('Nie podano typu aktualizacji');
        }

        if(!$force && !isset($this->patchTaskList[$args['type']])) {
            throw new MK_Exception('Nie można wykonać żądanej czynności.');
        }

        $phpVersion = floatval(phpversion());

        //@TODO checkupgrade $licence = new SpirbLicence(); $licence->checkUpgrade(); - to trzeba uzupełnić o tą funkjonalność  "checkUpgrade"

        $fh = fopen(MTM_FILE_LIST, 'a');
        $msg = '';
        $typeData = '';
        $endVersion = $startVersion = str_replace('.', '_', $this->currentVersion);

        // Wymuszanie ustawienia wersji aktualizacji ($force === true)
        if($force === true) {
            $startVersion = str_replace('.', '_', $this->releasedVersion);
            $endVersion = implode('_', str_split(str_pad(((int)str_replace(array ('_', '.'), '', $startVersion)) + 1, 3, 0, STR_PAD_LEFT)));
        }

        // Wybór rodzaju aktualizacji
        switch ($args['type']) {
            case 'patch':
                $typeData = 'stable';
                $msg .= "Uruchomiono mechanizm wgrywania poprawek stabilnych ({$startVersion}:{$endVersion})";
                break;
            case 'patch_rc':
                $typeData = 'rc_' . date('YmdHis');
                $msg .= "Uruchomiono mechanizm wgrywania poprawek kandydujących na stabilne ({$startVersion}:{$endVersion})";
                break;
            case 'patch_dev':
                $typeData = date('YmdHis');
                $msg .= "Uruchomiono mechanizm wgrywania poprawek niestabilnych ({$startVersion}:{$endVersion})";
                break;
            case 'upgrade':
                $typeData = 'stable';
                if($force !== true) {
                    $endVersion = str_replace('.', '_', $this->allowVersion);
                }
                $msg .= "Uruchomiono mechanizm aktualizaji z wersji {$startVersion} do nowej wersji: {$endVersion}";
                break;
        }

        fwrite($fh, "apply_madkom_pack {$this->licence} {$this->appName} {$startVersion} {$endVersion} {$typeData} " . APP_PATH . " {$phpVersion} \n");
        fclose($fh);

        // Utworzenie pliku status.log
        $fh = fopen(APP_STATUS_LOG, 'a');
        fwrite($fh, date("Y-m-d H:i:s") . " Update.php: {$msg}\n");
        fclose($fh);

        //@TODO dodawanie do logów : TableLogs::addLogDeprecated(0, 'updateApplication', array( 'type' => $args['type'], 'msg' => $msg ));

        return array (
            "type" => $args['type'],
            "message" => $msg
        );
    }

    /**
     * Pobiera informacje z pliku do którego dodawane są dane dotyczące bieżącej aktualizacji
     *
     * @return Array
     */
    public function getProgress()
    {
        sleep(5);
        return $this->readProgressFile();
    }

    /**
     * Odczytuje plik i zwraca wynik w postaci tablicy
     *
     * @return Array
     */
    public function readProgressFile()
    {
        $rows = array ();
        if(file_exists(APP_STATUS_LOG)) {
            preg_match_all($this->logRegExp, file_get_contents(APP_STATUS_LOG), $row);
            if(isset($row[3]) && isset($row[3][0])) {
                $lastKey = count($row[3]) - 1;
                for ($i = 0; $i <= $lastKey; $i++) {
                    if(strstr($row[3][$i], 'DEBUG: ') !== false) {
                        continue;
                    }
                    $rows[] = array (
                        'date' => $row[1][$i],
                        'description' => $row[3][$i]
                    );
                }
            }
        } else {
            $rows[] = array (
                'date' => date('Y-m-d H:i:s'),
                'description' => 'W chwili obecnej nie jest wykonywana żadna aktualizacja'
            );
        }
        return $rows;
    }

    /**
     * Odczytuje plik i zwraca wynik w postaci tablicy
     *
     * Pobiera liste informacji z pliku z logami od aktualizacji
     * zwraca ostatnie 20 logów
     *
     * @return array
     */
    public function getHistory()
    {
        $maxRecords = 20;
        $rows = array ();
        if(is_dir(MK_DIR_UPDATE_LOGS)) {
            $files = scandir(MK_DIR_UPDATE_LOGS);
            rsort($files);
            foreach ($files as $file) {
                preg_match_all($this->logRegExp, file_get_contents(MK_DIR_UPDATE_LOGS . DIRECTORY_SEPARATOR . $file), $row);
                if(!isset($row[3]) || !isset($row[3][0])) {
                    continue;
                }
                $lastKey = count($row[3]) - 1;
                $rows[] = array (
                    'date' => $row[1][$lastKey],
                    'description' => $row[3][$lastKey]
                );
                if(--$maxRecords <= 0) {
                    break;
                }
            }
        }

        return $rows;
    }

}