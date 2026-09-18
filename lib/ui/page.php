<?php

namespace Shef\Options\UI;

use Bitrix\Main\Result;
use Bitrix\Main\Error;

class Page
{
	const SKEEP = 'notClear';
	protected static $instance = null;
	protected $page = [];

	private $request;
	private $isSkeep = false;

	// region construct ////
	public static function getInstance()
	{
		if(!isset(static::$instance))
		{
			static::$instance = new static();
		}

		return static::$instance;
	}

	protected function __construct()
	{
		$this->initPage();
		$this->parseTypeArea();
	}
	// endregion ////
	// region Init ////
	protected function initPage(): void
	{
		$this->request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

		global $USER;

		if(
			!$USER->IsAuthorized() || isset($this->request[static::SKEEP])
		)
		{
			$this->isSkeep = true;
		}

		$this->page = [
			'url' => $this->request->getRequestedPage(),
			'urlItems' => explode('/', $this->request->getRequestedPage()),
			'typeArea' => [],
			'typeAreaStr' => '',
			'entityId' => 0,
			'isAdmin' => $USER->IsAdmin()
		];
	}

	protected function parseTypeArea(): void
	{
		// crm ////
		if(in_array('crm', $this->page['urlItems']))
		{
			$this->page['typeArea'][] = 'crm';
			// deal ////
			if(in_array('deal', $this->page['urlItems']))
			{
				$this->page['typeArea'][] = 'deal';
				// show ////
				if(in_array('details', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'show';
					$this->page['entityId'] = intval($this->page['urlItems'][4]);
					// list ////
				}
				elseif(in_array('list', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'list';
				}
			}// company ////
			elseif(in_array('company', $this->page['urlItems']))
			{
				$this->page['typeArea'][] = 'company';
				// show ////
				if(in_array('details', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'show';
					$this->page['entityId'] = intval($this->page['urlItems'][4]);
					// list ////
				}
				elseif(in_array('list', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'list';
				}
			}// contact ////
			elseif(in_array('contact', $this->page['urlItems']))
			{
				$this->page['typeArea'][] = 'contact';
				// show ////
				if(in_array('details', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'show';
					$this->page['entityId'] = intval($this->page['urlItems'][4]);
					// list ////
				}
				elseif(in_array('list', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'list';
				}
			}
		}
		// shop ////
		elseif(in_array('saleshub', $this->page['urlItems']))
		{
			//https://example.com/saleshub/orders/order/?ownerId=13&ownerTypeId=2&sessionId=6 ////
			// https://example.com/saleshub/orders/order/?orderId=12 ////
			$this->page['typeArea'][] = 'shop';
			// orders ////
			if(in_array('orders', $this->page['urlItems']))
			{
				$this->page['typeArea'][] = 'orders';
				if(in_array('order', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'show';
					$this->page['entityId'] = intval($this->request->get('orderId'));
					// list ////
				}
				else
				{
					$this->page['typeArea'][] = 'list';
				}
				
			}
		}
		elseif(in_array('shop', $this->page['urlItems']))
		{
			//https://example.com/shop/orders/details/11/?notClear=Y ////
			$this->page['typeArea'][] = 'shop';
			// orders ////
			if(in_array('orders', $this->page['urlItems']))
			{
				$this->page['typeArea'][] = 'orders';
				if(in_array('shipment', $this->page['urlItems']))
				{
					// shipment ////
					$this->page['typeArea'][] = 'shipment';
					// show ////
					if(in_array('details', $this->page['urlItems']))
					{
						$this->page['typeArea'][] = 'show';
						$this->page['entityId'] = intval($this->page['urlItems'][4]);
						// list ////
					}
					else
					{
						$this->page['typeArea'][] = 'list';
					}
				}
				elseif(in_array('payment', $this->page['urlItems']))
				{
					// payment ////
					$this->page['typeArea'][] = 'payment';
					// show ////
					if(in_array('details', $this->page['urlItems']))
					{
						$this->page['typeArea'][] = 'show';
						$this->page['entityId'] = intval($this->page['urlItems'][4]);
						// list ////
					}
					else
					{
						$this->page['typeArea'][] = 'list';
					}
				}// show ////
				elseif(in_array('details', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'show';
					$this->page['entityId'] = intval($this->page['urlItems'][4]);
					// list ////
				}
				else
				{
					$this->page['typeArea'][] = 'list';
				}
			}
		}
		// /bitrix/components/bitrix/crm.order.payment.voucher/slider.ajax.php ////
		// components-crm-order-payment-voucher-slider-ajax-php ////
		elseif(in_array('components', $this->page['urlItems']))
		{
			$this->page['typeArea'][] = 'components';
			if(in_array('crm.order.payment.voucher', $this->page['urlItems']))
			{
				// crm.order.payment.voucher ////
				$this->page['typeArea'][] = 'crm-order-payment-voucher';
				// show ////
				if(in_array('slider.ajax.php', $this->page['urlItems']))
				{
					$this->page['typeArea'][] = 'slider-ajax-php';
					$this->page['entityId'] = intval($this->page['urlItems'][4]);
				}
				else
				{
					$this->page['typeArea'][] = 'component';
				}
			}
		}

		$this->setTypeAreaStr();
	}

	public function getPage(): array
	{
		return $this->page;
	}

	public function setTypeArea(array $typeArea): void
	{
		$this->page['typeArea'] = $typeArea;
		$this->setTypeAreaStr();
	}

	protected function setTypeAreaStr(): void
	{
		$this->page['typeAreaStr'] = implode('-', $this->page['typeArea']);
	}

	public function getTypeArea(): string
	{
		return $this->page['typeAreaStr'];
	}

	// endregion ////
	// region Test ////
	public function isAjax(): bool
	{
		return(
			!(strpos($this->page['url'], 'ajax') === false)
			|| isset($this->request['IS_AJAX'])
		);
	}
	public function isSkeep(): bool
	{
		return $this->isSkeep;
	}

	public function isNew(): bool
	{
		return $this->isCopy() || $this->getEntityId() < 1;
	}

	public function isNoNew(): bool
	{
		return !$this->isNew();
	}

	public function isCopy(): bool
	{
		return isset($this->request['copy']);
	}



	public function isAdmin(): bool
	{
		return $this->page['isAdmin'];
	}

	public function getEntityId(): int
	{
		return (int)$this->page['entityId'];
	}
	// endregion ////
	// region Crm ////
	public function isCrm(): bool
	{
		return in_array('crm', $this->page['typeArea']);
	}

	public function isCrmDealShow(): bool
	{
		return $this->isCrm() && $this->getTypeArea() === 'crm-deal-show';
	}

	public function isCrmCompanyShow(): bool
	{
		return $this->isCrm() && $this->getTypeArea() === 'crm-company-show';
	}

	public function isCrmContactShow(): bool
	{
		return $this->isCrm() && $this->getTypeArea() === 'crm-contact-show';
	}
	// endregion ////
	// region Shop ////
	public function isShop(): bool
	{
		return in_array('shop', $this->page['typeArea']);
	}

	public function isShopOrderShow(): bool
	{
		return $this->isShop() && $this->getTypeArea() === 'shop-orders-show';
	}

	public function isShopOrderList(): bool
	{
		return $this->isShop() && $this->getTypeArea() === 'shop-orders-list';
	}

	public function isShopShipmentShow(): bool
	{
		return $this->isShop() && $this->getTypeArea() === 'shop-orders-shipment-show';
	}

	public function isShopPaymentShow(): bool
	{
		return $this->isShop() && $this->getTypeArea() === 'shop-orders-payment-show';
	}

	public function isComponentsOrderPaymentVoucherSliderAjaxShow(): bool
	{
		return $this->getTypeArea() === 'components-crm-order-payment-voucher-slider-ajax-php';
	}
	// endregion ////
}