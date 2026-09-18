<?php declare(strict_types=1);

namespace Shef\Options\Main\Options\Markdown;

class MarkdownExtra
	extends \Michelf\MarkdownExtra
{
	protected function _doFencedCodeBlocks_callback($matches)
	{
		$classname =& $matches[2];
		$attrs =& $matches[3];
		$codeBlock = $matches[4];
		
		if($this->code_block_content_func)
		{
			$codeBlock = call_user_func(
				$this->code_block_content_func,
				$codeBlock,
				$classname
			);
		}
		else
		{
			$codeBlock = htmlspecialchars($codeBlock, ENT_NOQUOTES);
		}
		
		$codeBlock = preg_replace_callback(
			'/^\n+/',
			[$this, '_doFencedCodeBlocks_newlines'],
			$codeBlock
		);
		
		$classes = [];
		$mainClass = '';
		if($classname !== '')
		{
			if ($classname[0] === '.')
			{
				$classname = substr($classname, 1);
			}
			$mainClass = mb_strtolower($classname);
			
			$classes[] = $this->code_class_prefix.$classname;
		}
		
		$attr = $this->doExtraAttributes(
			$this->code_attr_on_pre ? "pre" : "code",
			$attrs,
			null,
			$classes
		);
		
		$preAttr  = $this->code_attr_on_pre ? $attr : '';
		$codeAttr = $this->code_attr_on_pre ? '' : $attr;
		
		if(in_array($mainClass, ['php', 'css', 'js', 'bash', 'shell']))
		{
			$codeAttr .= ' data-lang="'.$mainClass.'"';
		}
		
		if(\Bitrix\Main\Loader::includeModule('shef.uiclear'))
		{
			$codeAttr .= sprintf(
				' style="background: %s"',
				\Shef\UiClear\Css\Color::gradient->value
			);
		}

		if($mainClass === 'php')
		{
			\Shef\Options\Main\Utils::highlightPhp($codeBlock);
		}
		
		$codeBlock  = "<pre$preAttr><code$codeAttr>$codeBlock</code></pre>";
		
		return "\n\n".$this->hashBlock($codeBlock)."\n\n";
	}
}