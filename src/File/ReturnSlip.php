<?php

namespace Message\Mothership\OrderReturn\File;

use Message\Cog\Filesystem\File;
use Message\Cog\Service\ContainerInterface;
use Message\Cog\Service\ContainerAwareInterface;
use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\Commerce\Order\Entity\Document\Document;

class ReturnSlip implements ContainerAwareInterface
{
	/**
	 * @var ContainerInterface
	 */
	protected $_container;

	const FILE_SUFFIX = '-return-slip';

	public function __construct(ContainerInterface $container)
	{
		$this->setContainer($container);
	}

	/**
	 * {@inheritDoc}
	 */
	public function setContainer(ContainerInterface $container)
	{
		$this->_container = $container;
	}

	public function save(OrderReturn $return)
	{
		$this->_container['filesystem']->mkdir($this->_getDirs());

		$html = $this->_getHtml('Message:Mothership:OrderReturn::return:return-slip', array(
			'merchant' => $this->_container->get('cfg')->merchant,
			'return' => $return
		));

		$filename = $return->id . self::FILE_SUFFIX;

		$this->_createFile($filename, $html);

		$path = $this->_getPath($filename);

		$document = new Document;
		$document->order = $return->item->order;
		$document->type = 'return-slip';
		$document->file = new File($path);

		$document = $this->_container['order.document.create']->create($document);

		return $document;
	}

	protected function _getDirs()
	{
		return array(
			'cog://data/return/',
			'cog://data/return/' . date('Y-m-d')
		);
	}

	/**
	 * [_getHtml description]
	 *
	 * @param  string $reference
	 * @param  string $params
	 *
	 * @return string
	 */
	protected function _getHtml($reference, $params)
	{
		return $this->_container['response_builder']
			->setRequest($this->_container['request'])
			->render($reference, $params)
			->getContent();
	}

	/**
	 * Create file containing contents. Automatically generates ID to use as file name
	 *
	 * @param string $name
	 * @param string $contents
	 *
	 * @throws \LogicException
	 *
	 * @return bool
	 */
	protected function _createFile($name, $contents)
	{
		$contents = (string) $contents;
		$path = $this->_getPath($name);

		if ($this->_container['filesystem']->exists($path)) {
			throw new \LogicException($path . " already exists, when it shouldn't");
		}

		$manager = $this->_container['filesystem.stream_wrapper_manager'];
		$handler = $manager::getHandler('cog');
		$path = $handler->getLocalPath($path);

		$this->_container['filesystem']->dumpFile($path, $contents);

		return true;
	}

	/**
	 * Create full path and extension for filename
	 *
	 * @param $filename
	 *
	 * @return string
	 */
	protected function _getPath($filename)
	{
		$dirs = $this->_getDirs();

		return array_pop($dirs) . '/' . $filename . '.html';
	}

}