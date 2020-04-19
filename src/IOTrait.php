<?php

declare(strict_types=1);

namespace Droath\HarvestToolkit;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Define the console application IO trait.
 */
trait IOTrait
{
    /**
     * Set an error on the output.
     *
     * @param string $message
     */
    protected function error(string $message): void
    {
        $this->output()->writeln("\n<error>[Error] {$message}</error>\n");
    }

    /**
     * Set an success on the output.
     *
     * @param string $message
     */
    protected function success(string $message): void
    {
        $this->output()->writeln("\n<info>[Success] {$message}</info>\n");
    }

    /**
     * Set an warning on the output.
     *
     * @param string $message
     */
    protected function warning(string $message): void
    {
        $this->output()->writeln("\n<comment>[Warning] {$message}</comment>\n");
    }

    /**
     * Ask for confirmation to a question.
     *
     * @param string $question
     * @param bool $default
     *
     * @return bool|mixed|string|null
     */
    protected function confirm(string $question, $default = true)
    {
        $defaultString = $default ? 'yes' : 'no';

        return $this->doAsk(new ConfirmationQuestion(
            $this->formatQuestion($question, $defaultString),
            $default
        ));
    }

    /**
     * Ask to choose based on a given set of options.
     *
     * @param string $question
     * @param array $choices
     * @param null $default
     *
     * @return bool|mixed|string|null
     */
    protected function choice(string $question, array $choices, $default = null)
    {
        return $this->doAsk(new ChoiceQuestion(
            $this->formatQuestion($question, $default),
            $choices,
            $default
        ));
    }

    /**
     * Ask a simple question.
     *
     * @param string $question
     * @param string|null $default
     * @param bool $required
     * @param bool $hidden
     *
     * @return bool|mixed|string|null
     */
    protected function ask(string $question, $default = null, $required = false, $hidden = false)
    {
        $question = new Question(
            $this->formatQuestion($question, $default),
            $default
        );
        $question->setHidden($hidden);

        if ($required) {
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException(
                        'A value is required!'
                    );
                }
                return $value;
            });
        }

        return $this->doAsk($question);
    }

    /**
     * Ask a question using a custom question object.
     *
     * @param \Symfony\Component\Console\Question\Question $question
     *
     * @return bool|mixed|string|null
     */
    protected function doAsk(Question $question)
    {
        return $this->getHelper('question')->ask(
            $this->input(),
            $this->output(),
            $question
        );
    }

    /**
     * Format the question and the default value.
     *
     * @param string $question
     * @param null $default
     *
     * @return string
     */
    protected function formatQuestion(string $question, $default = null): string
    {
        $question = "[?] {$question}";

        if (is_scalar($default)) {
            $question .= " [{$default}]";
        }

        return "\n<question>{$question}:</question>";
    }

    /**
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    protected function input(): InputInterface
    {
        return $this->container->get('input');
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    protected function output(): OutputInterface
    {
        return $this->container->get('output');
    }
}
