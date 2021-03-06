<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types = 1);

namespace Tardigrades\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Tardigrades\Entity\FieldType;
use Tardigrades\SectionField\SectionFieldInterface\FieldTypeManager;
use Tardigrades\SectionField\Service\FieldTypeManagerInterface;
use Tardigrades\SectionField\Service\FieldTypeNotFoundException;
use Tardigrades\SectionField\ValueObject\Id;

class DeleteFieldTypeCommand extends FieldTypeCommand
{
    /** @var QuestionHelper */
    private $questionHelper;

    /** @var FieldTypeManagerInterface */
    private $fieldTypeManager;

    public function __construct(
        FieldTypeManagerInterface $fieldTypeManager
    ) {
        $this->fieldTypeManager = $fieldTypeManager;

        parent::__construct('sf:delete-field-type');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Delete field type.')
            ->setHelp('Delete field type.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->questionHelper = $this->getHelper('question');

            $this->showInstalledFieldTypes($input, $output);
        } catch (FieldTypeNotFoundException $exception) {
            $output->writeln("Field type not found");
        }
    }

    private function showInstalledFieldTypes(InputInterface $input, OutputInterface $output): void
    {
        $fieldTypes = $this->fieldTypeManager->readAll();

        $this->renderTable($output, $fieldTypes, 'Installed field types');
        $this->deleteWhatRecord($input, $output);
    }

    private function deleteWhatRecord(InputInterface $input, OutputInterface $output): void
    {
        $fieldType = $this->getFieldType($input, $output);

        if ($fieldType->hasFields()) {
            $fields = PHP_EOL;
            foreach ($fieldType->getFields() as $field) {
                $fields .= ' - ' . $field->getHandle() . ':' . PHP_EOL;
            }

            $output->writeln(
                '<info>This FieldType has fields that use this type, delete them first. ' .
                $fields .
                '</info>'
            );
            return;
        }

        $output->writeln('<info>Record with id #' . $fieldType->getId() . ' will be deleted</info>');

        $sure = new ConfirmationQuestion('<comment>Are you sure?</comment> (y/n) ', false);

        if (!$this->questionHelper->ask($input, $output, $sure)) {
            $output->writeln('<comment>Cancelled, nothing deleted.</comment>');
            return;
        }

        $this->fieldTypeManager->delete($fieldType);

        $output->writeln('<info>Removed!</info>');
    }

    private function getFieldType(InputInterface $input, OutputInterface $output): FieldType
    {
        $question = new Question('<question>What record do you want to delete?</question> (#id): ');
        $question->setValidator(function ($id) {
            try {
                return $this->fieldTypeManager->read(Id::fromInt((int) $id));
            } catch (FieldTypeNotFoundException $exception) {
                return null;
            }
        });

        $fieldType = $this->questionHelper->ask($input, $output, $question);
        if (!$fieldType) {
            throw new FieldTypeNotFoundException();
        }
        return $fieldType;
    }
}
