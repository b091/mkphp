<?php
require_once('ProxyClassLoader.php');

/**
 * MK_Broker_Client
 *
 * Klasa do obsługi klienta, który łączy się do Brokera
 *
 * @category    MK_Broker
 * @package     MK_Broker_Client
 * @throws		MK_Exception
 */
class MK_Broker_Client {

	/**
	 * WSDL do Brokera
	 * @var string
	 */
	private $wsdlUrl = NULL;

	/**
	 * Login do połączenia z Brokerem
	 * @var string
	 */
	private $frontOfficeClientLogin = NULL;

	/**
	 * Hasło do połaczenia z Brokerem
	 * @var string
	 */
	private $frontOfficeClientPassword = NULL;

	/**
	 * Ustawienia dla SoapClient
	 * @var array
	 */
	private $clientSettings = array(
		'trace' => 1,
		'exceptions' => 1
	);

	/**
	 * SoapClient - nawiązane połączenie
	 * @var SoapClient
	 */
	public $soapClient = NULL;

	/**
	 * Konstruktor
	 *
	 * @param string        $login
	 * @param string        $password
	 * @param string        $url
	 * @param bool|\array   $settings
	 *
	 * @return \MK_Broker_Client
	 */
	public function __construct($login, $password, $url, $settings = false) {
		$this->frontOfficeClientLogin = $login;
		$this->frontOfficeClientPassword = $password;
		$this->wsdlUrl = $url;

		if($settings !== false) {
			$this->clientSettings = $settings;
		}

		return $this;
	}

	/**
	 * Magiczne wywowałanie metody $this->SoapClient->ClassName($args)
	 * Opakowane jest przez try-catch, więc od razu obsłużone są wyjątki i zwracany response.
	 *
	 * Przykład:
	 *  $this->authorize($param1, $param2, $param3);
	 *  $this->sendRegistries($registries);
	 * powoduje uruchomienie:
	 *  $this->soapClient->authorize($param1, $param2, $param3);
	 *  $this->soapClient->sendRegistries($registries);
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return mixed
	 * @throws MK_Exception
	 */
	public function __call($name, $arguments) {
		if($this->soapClient instanceof SoapClient) {
			try {
				return call_user_func_array(array($this->soapClient, $name), $arguments);
			}
			catch(Exception $e) {
				throw new MK_Exception($e->getMessage());
			}
		}
		else {
			throw new MK_Exception('Nie zostało nawiązane połączenie z Brokerem!');
		}
    }

	/**
	 * Nawiązanie połączenia z Brokerem przy użyciu webservice [SoapClient]
	 *
	 * @throws MK_Exception
	 * @return string
	 */
	public function connect() {
		$this->soapClient = new SoapClient($this->wsdlUrl, $this->clientSettings);
		// Autoryzacja na Brokerze - weryfikacja użytkownika i hasła
		$authorizeResponse = $this->soapClient->authorize(new authorize($this->frontOfficeClientLogin, $this->frontOfficeClientPassword));
		if($authorizeResponse->return === false) {
			throw new MK_Exception('Nie udało się poprawnie zalogować do Brokera');
		}
		return $authorizeResponse->return;
	}

	/**
	 * Wylogowanie z Brokera za pomocą webservice
	 *
	 * @throws MK_Exception
	 * @return string
	 */
	public function logout() {
		return $this->soapClient->logout(new logout());
	}

}