<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Twig;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Environment extends \Twig_Environment
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(\Twig_Environment $twig, EventDispatcherInterface $eventDispatcher)
    {
        $this->twig = $twig;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function render($name, array $context = [])
    {
        // twigファイルのソースコードを読み込み文字列化する.
        $source = $this->twig->getLoader()
            ->getSourceContext($name)
            ->getCode();

        // プラグインにはテンプレートファイル名, 文字列化されたtwigファイル, パラメータを渡す.
        $event = new TemplateEvent($name, $source, $context);

        preg_match('/{% *extends *[\'\"]default_frame\.twig[\'\"] *%}/', $source, $matches);
        if (isset($matches[0])) {
            $this->eventDispatcher->dispatch('default_frame.twig', $event);
        }

        preg_match('/{% *extends *[\'\"]\@admin\/default_frame\.twig[\'\"] *%}/', $source, $matches);
        if (isset($matches[0])) {
            $this->eventDispatcher->dispatch('@admin\default_frame.twig', $event);
        }

        preg_match_all('/{% *include *[\'\"](.+)[\'\"] *(with.+)? *%}/', $source, $matches, PREG_OFFSET_CAPTURE);
        if (isset($matches[1])) {
            foreach ($matches[1] as $includeTwigName) {
                $this->eventDispatcher->dispatch($includeTwigName[0], $event);
            }
        }

        // テンプレートフックポイントの実行.
        $this->eventDispatcher->dispatch($name, $event);

        // プラグインで変更された文字列から, テンプレートオブジェクトを生成.
        $template = $this->twig->createTemplate($event->getSource());

        // レンダリング実行.
        $content = $template->render($event->getParameters());

        return $content;
    }
}
