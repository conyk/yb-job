<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2019-01-05
 * Time: 09:54
 */

namespace Inhere\Console\Traits;

use Inhere\Console\Component\Style\Style;
use Inhere\Console\Face\CommandInterface;
use Inhere\Console\Util\FormatUtil;
use Inhere\Console\Util\Helper;

/**
 * Trait ApplicationHelpTrait
 * @package Inhere\Console\Traits
 */
trait ApplicationHelpTrait
{
    /**
     * show the application version information
     */
    public function showVersionInfo(): void
    {
        $os         = \PHP_OS;
        $date       = \date('Y.m.d');
        $logo       = '';
        $name       = $this->getConfig('name', 'Console Application');
        $version    = $this->getConfig('version', 'Unknown');
        $publishAt  = $this->getConfig('publishAt', 'Unknown');
        $updateAt   = $this->getConfig('updateAt', 'Unknown');
        $phpVersion = \PHP_VERSION;

        if ($logoTxt = $this->getLogoText()) {
            $logo = Helper::wrapTag($logoTxt, $this->getLogoStyle());
        }

        /** @var \Inhere\Console\IO\Output $out */
        $out = $this->output;
        $out->aList([
            "$logo\n  <info>{$name}</info>, Version <comment>$version</comment>\n",
            'System Info'      => "PHP version <info>$phpVersion</info>, on <info>$os</info> system",
            'Application Info' => "Update at <info>$updateAt</info>, publish at <info>$publishAt</info>(current $date)",
        ], null, [
            'leftChar' => '',
            'sepChar'  => ' :  '
        ]);
    }

    /***************************************************************************
     * some information for the application
     ***************************************************************************/

    /**
     * show the application help information
     * @param string $command
     */
    public function showHelpInfo(string $command = ''): void
    {
        /** @var \Inhere\Console\IO\Input $in */
        $in = $this->input;

        // display help for a special command
        if ($command) {
            $in->setCommand($command);
            $in->setSOpt('h', true);
            $in->clearArgs();
            $this->dispatch($command);
            return;
        }

        $sep    = $this->delimiter;
        $script = $in->getScript();

        /** @var \Inhere\Console\IO\Output $out */
        $out = $this->output;
        $out->helpPanel([
            'usage'   => "$script <info>{command}</info> [--opt -v -h ...] [arg0 arg1 arg2=value2 ...]",
            'example' => [
                "$script test (run a independent command)",
                "$script home{$sep}index (run a command of the group)",
                "$script help {command} (see a command help information)",
                "$script home{$sep}index -h (see a command help of the group)",
            ]
        ], false);
    }

    /**
     * show the application command list information
     */
    public function showCommandList()
    {
        /** @var \Inhere\Console\IO\Input $input */
        $input = $this->input;
        /** @var \Inhere\Console\IO\Output $output */
        $output = $this->output;
        // has option: --auto-completion
        $autoComp = $input->getBoolOpt('auto-completion');
        // has option: --shell-env
        $shellEnv = (string)$input->getLongOpt('shell-env', '');

        // php bin/app list --only-name
        if ($autoComp && $shellEnv === 'bash') {
            $this->dumpAutoCompletion($shellEnv, []);
            return;
        }

        $script        = $this->getScriptName();
        $hasGroup      = $hasCommand = false;
        $controllerArr = $commandArr = [];
        $placeholder   = 'No description of the command';

        // all console controllers
        if ($controllers = $this->controllers) {
            $hasGroup = true;
            \ksort($controllers);
        }

        // all independent commands, Independent, Single, Alone
        if ($commands = $this->commands) {
            $hasCommand = true;
            \ksort($commands);
        }

        // add split title on both exists.
        if (!$autoComp && $hasCommand && $hasGroup) {
            $commandArr[]    = \PHP_EOL . '- <bold>Alone Commands</bold>';
            $controllerArr[] = \PHP_EOL . '- <bold>Group Commands</bold>';
        }

        foreach ($controllers as $name => $controller) {
            /** @var \Inhere\Console\AbstractCommand $controller */
            $desc    = $controller::getDescription() ?: $placeholder;
            $aliases = $this->getCommandAliases($name);
            $extra   = $aliases ? Helper::wrapTag(
                ' [alias: ' . \implode(',', $aliases) . ']',
                'info'
            ) : '';

            // collect
            $controllerArr[$name] = $desc . $extra;
        }

        if (!$hasGroup && $this->isDebug()) {
            $controllerArr[] = '... Not register any group command(controller)';
        }

        foreach ($commands as $name => $command) {
            $desc = $placeholder;

            /** @var \Inhere\Console\AbstractCommand $command */
            if (\is_subclass_of($command, CommandInterface::class)) {
                $desc = $command::getDescription() ?: $placeholder;
            } elseif ($msg = $this->getCommandMetaValue($name, 'description')) {
                $desc = $msg;
            } elseif (\is_string($command)) {
                $desc = 'A handler : ' . $command;
            } elseif (\is_object($command)) {
                $desc = 'A handler by ' . \get_class($command);
            }

            $aliases           = $this->getCommandAliases($name);
            $extra             = $aliases ? Helper::wrapTag(' [alias: ' . \implode(',', $aliases) . ']', 'info') : '';
            $commandArr[$name] = $desc . $extra;
        }

        if (!$hasCommand && $this->isDebug()) {
            $commandArr[] = '... Not register any alone command';
        }

        // built in commands
        $internalCommands = static::$internalCommands;

        if ($autoComp && $shellEnv === 'zsh') {
            $map = \array_merge($internalCommands, $controllerArr, $commandArr);
            $this->dumpAutoCompletion('zsh', $map);
            return;
        }

        \ksort($internalCommands);

        // built in options
        $internalOptions = FormatUtil::alignOptions(self::$internalOptions);

        $output->mList([
            'Usage:'              => "$script <info>{command}</info> [--opt -v -h ...] [arg0 arg1 arg2=value2 ...]",
            'Options:'            => $internalOptions,
            'Internal Commands:'  => $internalCommands,
            'Available Commands:' => \array_merge($controllerArr, $commandArr),
        ], [
            'sepChar' => '  ',
        ]);

        unset($controllerArr, $commandArr, $internalCommands);
        $output->write("More command information, please use: <cyan>$script {command} -h</cyan>");
    }

    /**
     * zsh:
     *  php examples/app --auto-completion  --shell-env zsh
     *  php examples/app --auto-completion --shell-env zsh --gen-file
     *  php examples/app --auto-completion --shell-env zsh --gen-file stdout
     * bash:
     *  php examples/app --auto-completion --shell-env bash
     *  php examples/app --auto-completion --shell-env bash --gen-file
     *  php examples/app --auto-completion --shell-env bash --gen-file stdout
     * @param string $shellEnv
     * @param array  $data
     */
    protected function dumpAutoCompletion(string $shellEnv, array $data): void
    {
        /** @var \Inhere\Console\IO\Input $input */
        $input = $this->input;
        /** @var \Inhere\Console\IO\Output $output */
        $output = $this->output;

        // info
        $glue     = ' ';
        $genFile  = (string)$input->getLongOpt('gen-file');
        $filename = 'auto-completion.' . $shellEnv;
        $tplDir   = \dirname(__DIR__, 2) . '/res/templates';

        if ($shellEnv === 'bash') {
            $tplFile = $tplDir . '/bash-completion.tpl';
            $list    = \array_merge(
                $this->getCommandNames(),
                $this->getControllerNames(),
                $this->getInternalCommands()
            );
        } else {
            $glue    = \PHP_EOL;
            $list    = [];
            $tplFile = $tplDir . '/zsh-completion.tpl';
            foreach ($data as $name => $desc) {
                $list[] = $name . ':' . \str_replace(':', '\:', $desc);
            }
        }

        $commands = \implode($glue, $list);

        // dump to stdout.
        if (!$genFile) {
            $output->write($commands, true, false, ['color' => false]);
            return;
        }

        if ($shellEnv === 'zsh') {
            $commands = "'" . \implode("'\n'", $list) . "'";
            $commands = Style::stripColor($commands);
        }

        // dump at script file
        $binName = $input->getBinName();
        $tplText = \file_get_contents($tplFile);
        $content = \strtr($tplText, [
            '{{version}}'    => $this->getVersion(),
            '{{filename}}'   => $filename,
            '{{commands}}'   => $commands,
            '{{binName}}'    => $binName,
            '{{datetime}}'   => \date('Y-m-d H:i:s'),
            '{{fmtBinName}}' => \str_replace('/', '_', $binName),
        ]);

        // dump to stdout
        if ($genFile === 'stdout') {
            \file_put_contents('php://stdout', $content);
            return;
        }

        $targetFile = $input->getPwd() . '/' . $filename;
        $output->write(['Target File:', $targetFile, '']);

        if (\file_put_contents($targetFile, $content) > 10) {
            $output->success("O_O! Generate $filename successful!");
        } else {
            $output->error("O^O! Generate $filename failure!");
        }
    }
}
