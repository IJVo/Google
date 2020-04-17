<?php
declare(strict_types=1);
/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Google\Dialog;

use Kdyby\Google\Configuration;
use Kdyby\Google\Google;
use Nette\Application;
use Nette\Application\Responses;
use Nette\Application\UI\Component;
use Nette\Http\UrlScript;

/**
 * @author Mikulas Dite <rullaf@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onResponse(AbstractDialog $dialog)
 */
abstract class AbstractDialog extends Component
{

	/**
	 * @var array of function(AbstractDialog $dialog)
	 */
	public $onResponse = [];

	/**
	 * @var Google
	 */
	protected $google;

	/**
	 * @var Configuration
	 */
	protected $config;

	/**
	 * @var \Kdyby\Google\SessionStorage
	 */
	protected $session;

	/**
	 * @var Application\UI\Link|UrlScript
	 */
	protected $returnUri;


	/**
	 * @param Google $google
	 */
	public function __construct(Google $google)
	{
		$this->google = $google;
		$this->config = $google->getConfig();
		$this->session = $google->getSession();

		// Remove for remove constructor in Nette\ComponentModel\Component
		//parent::__construct();
	}


	/**
	 * @return Google
	 */
	public function getGoogle()
	{
		return $this->google;
	}


	/**
	 * @return Application\UI\Link|UrlScript
	 */
	protected function getReturnLink()
	{
		if (!$this->returnUri) {
			$this->returnUri = $this->google->getReturnLink($this);
		}

		return $this->returnUri;
	}


	/**
	 * @return UrlScript
	 */
	abstract public function getUrl();


	/**
	 * @throws Application\AbortException
	 */
	public function open()
	{
		$this->session->last_request = $this->getPresenter()->storeRequest();
		$this->presenter->redirectUrl($this->getUrl());
	}


	/**
	 * Opens the dialog.
	 */
	public function handleOpen()
	{
		$this->open();
	}


	/**
	 * Google get's the url for this handle when redirecting to login dialog.
	 * It automatically calls the onResponse event.
	 *
	 * You don't have to redirect, the request before the auth process will be restored automatically.
	 * @crossOrigin
	 */
	public function handleResponse()
	{
		$this->google->getUser(); // check the received parameters and save user
		$this->onResponse($this);

		if (!empty($this->session->last_request)) {
			$presenter = $this->getPresenter();

			try {
				$presenter->restoreRequest($this->session->last_request);
			} catch (Application\AbortException $e) {
				$refl = new \ReflectionProperty('Nette\Application\UI\Presenter', 'response');
				$refl->setAccessible(true);

				$response = $refl->getValue($presenter);
				if ($response instanceof Responses\ForwardResponse) {
					$forwardRequest = $response->getRequest();

					$params = $forwardRequest->getParameters();
					unset($params['do']); // remove the signal to the google component
					$forwardRequest->setParameters($params);
				}

				$presenter->sendResponse($response);
			}
		}

		$this->presenter->redirect('this', ['state' => null, 'code' => null]);
	}
}
