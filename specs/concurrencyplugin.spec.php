<?php
use Peridot\Concurrency\ConcurrencyPlugin;
use Peridot\Console\InputDefinition;
use Peridot\Console\Environment;
use Peridot\Console\Command;
use Peridot\Core\Suite;
use Peridot\Configuration;
use Peridot\Reporter\ReporterFactory;
use Peridot\Runner\Runner;
use Prophecy\Argument;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Evenement\EventEmitter;

describe('ConcurrencyPlugin', function () {
    beforeEach(function () {
        $this->emitter = new EventEmitter();
        $this->plugin = new ConcurrencyPlugin($this->emitter);
        $this->definition = new InputDefinition();
        $this->environment = new Environment($this->definition, $this->emitter, []);
    });

    context('when peridot.start event is emitted', function () {
        it('should register a --concurrent option', function () {
            $this->emitter->emit('peridot.start', [$this->environment]);
            expect($this->definition->hasOption('concurrent'))->to->be->true;
        });
    });

    /**
     * Helper to ensure configuration and app are in place.
     */
    $configure = function () {
        $this->app = $this->getProphet()->prophesize('Peridot\Console\Application');
        $this->config = new Configuration();
        $this->emitter->emit('peridot.configure', [$this->config, $this->app->reveal()]);
    };

    context('when peridot.execute event is emitted', function () use ($configure) {
        beforeEach($configure);

        beforeEach(function () {
            $this->emitter->emit('peridot.start', [$this->environment]);
        });

        afterEach(function () {
            $this->getProphet()->checkPredictions();
        });

        it('should store a reference to input object' , function () {
            $input = new StringInput('');
            $input->bind($this->definition);
            $this->emitter->emit('peridot.execute', [$input]);
            expect($this->plugin->getInput())->to->equal($input);
        });

        it('should set the stream select runner if concurrency is enabled', function () {
            $input = new StringInput('--concurrent');
            $input->bind($this->definition);
            $this->emitter->emit('peridot.execute', [$input]);
            $type = 'Peridot\Concurrency\Runner\StreamSelect\StreamSelectRunner';
            $this->app->setRunner(Argument::type($type))->shouldHaveBeenCalled();
        });

        it('should not set the stream select runner if concurrency is disabled', function () {
            $input = new StringInput('');
            $input->bind($this->definition);
            $this->emitter->emit('peridot.execute', [$input]);
            $type = 'Peridot\Concurrency\Runner\StreamSelect\StreamSelectRunner';
            $this->app->setRunner(Argument::type($type))->shouldNotHaveBeenCalled();
        });
    });

    context('when peridot.load event is emitted', function () use ($configure) {

        beforeEach($configure);

        beforeEach(function () {
            $suite = new Suite("suite", function () {});
            $configuration = new Configuration();
            $runner = new Runner($suite, $configuration, $this->emitter);
            $factory = new ReporterFactory($configuration, new NullOutput(), $this->emitter);
            $this->command = new Command($runner, $configuration, $factory, $this->emitter);
            $this->configuration = $configuration;
        });

        it('should set the suite loader if conncurrent option is set', function () {
            $this->emitter->emit('peridot.start', [$this->environment]);
            $input = new StringInput('--concurrent');
            $input->bind($this->definition);
            $this->emitter->emit('peridot.execute', [$input]);
            $this->emitter->emit('peridot.load', [$this->command, $this->configuration]);
            $loader = $this->command->getLoader();
            expect($loader)->to->be->an->instanceof('Peridot\Concurrency\SuiteLoader');
        });

        it('should not set the suite loader if concurrent options is not set', function () {
            $this->emitter->emit('peridot.start', [$this->environment]);
            $input = new StringInput('');
            $input->bind($this->definition);
            $this->emitter->emit('peridot.execute', [$input]);
            $this->emitter->emit('peridot.load', [$this->command, $this->configuration]);
            $loader = $this->command->getLoader();
            expect($loader)->to->be->an->instanceof('Peridot\Runner\SuiteLoader');
        });
    });

    context('when peridot.configure event is fired', function () use ($configure) {
        beforeEach($configure);

        it('should store references to the configuration and application objects', function () {
            expect($this->plugin->getConfiguration())->to->equal($this->config);
            expect($this->plugin->getApplication())->to->equal($this->app->reveal());
        });
    });
});
