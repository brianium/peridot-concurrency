<?php
namespace Peridot\Concurrency\Runner\StreamSelect;

/**
 * A WorkerInterface defines the contract for a worker designed
 * to start and manage a single process.
 *
 * @package Peridot\Concurrency\Runner\StreamSelect
 */
interface WorkerInterface
{
    /**
     * Start the worker process.
     *
     * @return void
     */
    public function start();

    /**
     * Return the stream that the process receives input on.
     *
     * @return resource
     */
    public function getInputStream();

    /**
     * Return the stream that the process writes to. Returns
     * the stream as a non-blocking resource.
     *
     * @return resource
     */
    public function getOutputStream();

    /**
     * Return the stream that the process writes errors to. Returns
     * the stream as a non-blocking resource.
     *
     * @return resource
     */
    public function getErrorStream();

    /**
     * Run the given test path against the worker process.
     *
     * @param string $testPath
     * @return void
     */
    public function run($testPath);

    /**
     * Returns whether or not the worker is currently
     * running a test.
     *
     * @return bool
     */
    public function isRunning();

    /**
     * Returns whether or not the worker has started
     * a process.
     *
     * @return bool
     */
    public function isStarted();
}
