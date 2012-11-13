<?php
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

set_time_limit(0);

$app = require_once __DIR__.'/bootstrap.php';

/** @var $application Knp\Console\Application */
$application = $app['console'];

$application
    ->register('hash-password')
    ->setDescription('Hash a password using bcrypt')
    ->setDefinition(new InputDefinition([
        new InputArgument('rawPassword',
            InputArgument::REQUIRED,
            'The raw password to convert to a bcrypt password with salt')
    ]))
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $raw_password = $input->getArgument('rawPassword');
        /** @var $encoder \Game\Util\BcryptPasswordEncoder */
        $encoder = $app['security.encoder.digest'];
        $output->writeln("Hashing password \"" . $raw_password . "\"");
        $output->writeln($encoder->encodePassword($raw_password, null));
    });

$application
    ->register('verify-password')
    ->setDescription('Validate a password using bcrypt')
    ->setDefinition(new InputDefinition([
        new InputArgument('rawPassword',
            InputArgument::REQUIRED,
            'The plain text password to check'),
        new InputArgument('hashedPassword',
            InputArgument::REQUIRED,
            'The hashed password')
    ]))
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $raw_password = $input->getArgument('rawPassword');
        $hashed_password = $input->getArgument('hashedPassword');
        /** @var $encoder \Game\Util\BcryptPasswordEncoder */
        $encoder = $app['security.encoder.digest'];
        $isValid = $encoder->isPasswordValid($hashed_password, $raw_password, null);
        $output->writeln("The hashed password: " . $hashed_password);
        $output->writeln("The result is: " . ($isValid ? "true" : "false"));
    });

$application->run();


?>