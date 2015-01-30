<?php
namespace Peridot\Concurrency\Runner\StreamSelect\Message;

use Exception;
use Peridot\Concurrency\Runner\StreamSelect\Model\Exception as ConcurrencyException;
use Peridot\Concurrency\Runner\StreamSelect\Model\Suite;
use Peridot\Concurrency\Runner\StreamSelect\Model\Test;
use Peridot\Core\Test as CoreTest;
use Peridot\Core\AbstractTest;

class TestMessage extends Message
{
    /**
     * @var int
     */
    const TEST_PENDING = 2;

    /**
     * @var int
     */
    const TEST_PASS = 1;

    /**
     * @var int
     */
    const TEST_FAIL = 0;

    /**
     * The Data to be serialized. Indexes represent specific pieces of the
     * message.
     *
     * 0 - a single character showing type: 's' for suite, 't' for test
     * 1 - an event name - i.e suite.start, test.pending, etc.
     * 2 - the test description
     * 3 - the test title
     * 4 - the test status
     * 5 - an exception message if available
     * 6 - an exception trace as string if available
     * 7 - the exception class name
     *
     * @var array
     */
    protected $data;

    /**
     * A buffer for storing incoming test message data.
     *
     * @var string
     */
    private $buffer = '';

    /**
     * @param resource $resource
     * @param int $chunkSize
     */
    public function __construct($resource, $chunkSize = 4096)
    {
        parent::__construct($resource, $chunkSize);
        $this->data = [null, null, null, null, null, null, null, null];
        $this->on('data', [$this, 'onData']);
    }

    /**
     * Include test information in the message.
     *
     * @param AbstractTest $test
     * @return $this
     */
    public function setTest(AbstractTest $test)
    {
        $this->data[0] = $this->getTypeChar($test);
        $this->data[2] = $this->packString($test->getDescription());
        $this->data[3] = $this->packString($test->getTitle());
        return $this;
    }

    /**
     * Include test exception information in the message.
     *
     * @param Exception $exception
     * @return $this
     */
    public function setException(Exception $exception)
    {
        $this->data[5] = $this->packString($exception->getMessage());
        $this->data[6] = $this->packString($exception->getTraceAsString());
        $this->data[7] = get_class($exception);
        return $this;
    }

    /**
     * Include an event name in the message.
     *
     * @param string $eventName
     * @return $this
     */
    public function setEvent($eventName)
    {
        $this->data[1] = $this->packString($eventName);
        return $this;
    }

    /**
     * Include the status in the test message.
     *
     * @param int $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->data[4] = $status;
        return $this;
    }

    /**
     * Write the test message. If content is supplied it will
     * be used instead of the internal serialized data structure.
     *
     * @param string $content
     */
    public function write($content = '')
    {
        if (! $content) {
            $content = serialize($this->data);
        }
        parent::write($content);
    }

    /**
     * Handle data received by this message. When complete test messages come in they
     * will be parsed and emitted. When a complete message is received it relays the event
     * name that was received and sends a last argument that is the unpacked message.
     *
     * @param $data
     * @return void
     */
    public function onData($data)
    {
        $this->buffer .= $data;
        $delimiterPosition = strpos($this->buffer, "\n");

        while ($delimiterPosition !== false) {
            $testMessage = substr($this->buffer, 0, $delimiterPosition);
            $unpacked = unserialize($testMessage);
            $test = $this->hydrateTest($unpacked);
            $this->emitTest($test, $unpacked);
            $this->buffer = substr($this->buffer, $delimiterPosition + 1);
            $delimiterPosition = strpos($this->buffer, "\n");
        }
    }

    /**
     * Hydrate a test from an unpacked test message.
     *
     * @param array $unpacked
     * @return \Peridot\Core\TestInterface
     */
    private function hydrateTest(array $unpacked)
    {
        $description = $this->unpackString($unpacked[2]);
        $title = $this->unpackString($unpacked[3]);
        $test = $unpacked[0] == 't' ? new Test($description) : new Suite($description);
        $test->setTitle($title);
        return $test;
    }

    /**
     * Emit an appropriate test event. If an exception is included in message
     * data it will be marshaled into an Exception model.
     *
     * @param AbstractTest $test
     * @param array $unpacked
     */
    private function emitTest(AbstractTest $test, array $unpacked)
    {
        $args = [$test];
        $event = $this->unpackString($unpacked[1]);
        if ($event == 'test.failed') {
            $exception = new ConcurrencyException($this->unpackString($unpacked[5]));
            $exception
                ->setTraceAsString($this->unpackString($unpacked[6]))
                ->setType($unpacked[7]);
            $args[] = $exception;
        }
        $args[] = $unpacked;
        $this->emit($event, $args);
    }

    /**
     * Get a single char used for identifying the type of AbstractTest
     * being used in the message.
     *
     * @param AbstractTest $test
     * @return string
     */
    private function getTypeChar(AbstractTest $test)
    {
        if ($test instanceof CoreTest) {
            return 't';
        }

        return 's';
    }

    /**
     * Replace new lines with a format that does not conflict with
     * parsing a test message.
     *
     * @param $str
     * @return string
     */
    private function packString($str)
    {
        $char = '\u0007';
        $char = json_decode('"' . $char . '"');
        return str_replace("\n", $char, $str);
    }

    /**
     * Replaces new line replacements with an actual new line.
     *
     * @param $str
     * @return string
     */
    private function unpackString($str)
    {
        $char = '\u0007';
        $char = json_decode('"' . $char . '"');
        return str_replace($char, "\n", $str);
    }
} 
