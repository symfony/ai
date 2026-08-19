.. card:
    title: Generator and Reviewer Loop
    description: Pair a generating agent with a reviewing agent that validates its findings over several iterations.
    icon: shield-search
    components: Agent

Validate Agent Output with a Generator and Reviewer Loop
========================================================

A single agent asked to both produce work and judge it tends to over-report: it accepts its own
speculative output. Giving the two jobs to separate agents curbs that — the reviewer judges each
finding from a clean context and with its own skeptical instructions, instead of rubber-stamping
what it just generated. In this guide you will build an adversarial loop where a **generating**
agent proposes findings and a separate **reviewing** agent confirms or rejects each one, iterating a
few times and feeding earlier verdicts back so the generator stops repeating itself.

The running example is a security auditor — a generator that hunts for vulnerabilities in Symfony
code and a reviewer that culls the false positives — but the same generator/reviewer pattern applies
to any task where precision matters more than a single pass can deliver: drafting and fact-checking,
code generation and critique, or extraction and validation.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* A platform that supports :doc:`structured output <../components/platform>`
* OpenAI API key (or any other supported platform)

Step 1: Install Packages
------------------------

Install the Platform and Agent components via Composer::

    composer require symfony/ai-platform symfony/ai-agent

Step 2: Model the Findings
--------------------------

Both agents exchange typed data rather than free text, so the loop can filter and compare results
in plain PHP. Structured output populates these classes directly from the model response::

    namespace App\Audit;

    enum Severity: string
    {
        case Low = 'low';
        case Medium = 'medium';
        case High = 'high';
        case Critical = 'critical';
    }

    final class Finding
    {
        public function __construct(
            public string $title,
            public string $location,
            public Severity $severity,
            public string $explanation,
            public float $confidence,
        ) {
        }
    }

    final class FindingList
    {
        /**
         * @param Finding[] $findings
         */
        public function __construct(
            public array $findings,
        ) {
        }
    }

    final class Verdict
    {
        public function __construct(
            public bool $confirmed,
            public string $reasoning,
        ) {
        }
    }

The generator returns a ``FindingList``; the reviewer returns one ``Verdict`` per finding. The
``confidence`` field lets the loop drop weak candidates before they ever reach the reviewer.

Step 3: Create the Generator and Reviewer Agents
------------------------------------------------

Each role is a regular :class:`Symfony\\AI\\Agent\\Agent` with a
:class:`Symfony\\AI\\Agent\\InputProcessor\\SystemPromptInputProcessor` that defines its job.
Structured output is resolved by the :class:`Symfony\\AI\\Platform\\StructuredOutput\\PlatformSubscriber`,
so register it on the platform's event dispatcher::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
    use Symfony\AI\Platform\Bridge\OpenAi\Factory;
    use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new PlatformSubscriber());

    $platform = Factory::createPlatform($apiKey, eventDispatcher: $dispatcher);

    $generator = new Agent(
        $platform,
        'gpt-4o-mini',
        [new SystemPromptInputProcessor(
            'You are a security auditor for Symfony applications. Report concrete, '
            .'exploitable vulnerabilities. For each finding set a confidence between '
            .'0 and 1 reflecting how sure you are it is real.',
        )],
        name: 'generator',
    );

    $reviewer = new Agent(
        $platform,
        'gpt-4o-mini',
        [new SystemPromptInputProcessor(
            'You are a skeptical security reviewer. Decide whether a reported finding '
            .'is a true positive. Reject anything speculative, already mitigated, or not '
            .'actually reachable by an attacker, and explain your reasoning briefly.',
        )],
        name: 'reviewer',
    );

Step 4: Build the Generate-Review Loop
--------------------------------------

The loop is plain PHP around the two agents. Each iteration asks the generator for candidates,
drops the ones below the confidence floor, and sends every new candidate to the reviewer. Confirmed
findings — and the reviewer's reason for each rejection — are fed back into the next generator
prompt, so the generator stops re-reporting settled findings and uses the rejection reasons to steer
clear of similar false positives::

    namespace App\Audit;

    use Symfony\AI\Agent\AgentInterface;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    final readonly class ReviewLoop
    {
        public function __construct(
            private AgentInterface $generator,
            private AgentInterface $reviewer,
            private int $maxIterations = 3,
            private float $minConfidence = 0.6,
        ) {
        }

        /**
         * @return list<Finding>
         */
        public function run(string $code): array
        {
            $confirmed = [];
            $rejected = [];
            $rejectionReasons = [];

            for ($iteration = 1; $iteration <= $this->maxIterations; ++$iteration) {
                $newlyConfirmed = 0;

                foreach ($this->generate($code, $confirmed, $rejectionReasons) as $finding) {
                    if ($finding->confidence < $this->minConfidence) {
                        continue;
                    }

                    if ($this->isKnown($finding, $confirmed) || $this->isKnown($finding, $rejected)) {
                        continue;
                    }

                    $verdict = $this->review($code, $finding);
                    if ($verdict->confirmed) {
                        $confirmed[] = $finding;
                        ++$newlyConfirmed;
                    } else {
                        $rejected[] = $finding;
                        $rejectionReasons[] = $finding->title.' ('.$finding->location.'): '.$verdict->reasoning;
                    }
                }

                if (0 === $newlyConfirmed) {
                    break;
                }
            }

            return $confirmed;
        }

        /**
         * @param list<Finding> $confirmed
         * @param list<string>  $rejectionReasons
         *
         * @return list<Finding>
         */
        private function generate(string $code, array $confirmed, array $rejectionReasons): array
        {
            $prompt = 'Audit this Symfony code for security vulnerabilities:'.\PHP_EOL.\PHP_EOL.$code;

            if ([] !== $confirmed) {
                $prompt .= \PHP_EOL.\PHP_EOL.'Already confirmed, do not report again:'
                    .\PHP_EOL.$this->summarize($confirmed);
            }

            if ([] !== $rejectionReasons) {
                $prompt .= \PHP_EOL.\PHP_EOL.'Already rejected as false positives, with the reason, do not report again:'
                    .\PHP_EOL.'- '.\implode(\PHP_EOL.'- ', $rejectionReasons);
            }

            /** @var FindingList $list */
            $list = $this->generator
                ->call(new MessageBag(Message::ofUser($prompt)), ['response_format' => FindingList::class])
                ->getContent();

            return $list->findings;
        }

        private function review(string $code, Finding $finding): Verdict
        {
            $prompt = 'Is this reported finding a true positive?'.\PHP_EOL.\PHP_EOL
                .'Title: '.$finding->title.\PHP_EOL
                .'Location: '.$finding->location.\PHP_EOL
                .'Explanation: '.$finding->explanation.\PHP_EOL.\PHP_EOL
                .'Code under audit:'.\PHP_EOL.$code;

            /** @var Verdict $verdict */
            $verdict = $this->reviewer
                ->call(new MessageBag(Message::ofUser($prompt)), ['response_format' => Verdict::class])
                ->getContent();

            return $verdict;
        }

        /**
         * @param list<Finding> $known
         */
        private function isKnown(Finding $finding, array $known): bool
        {
            foreach ($known as $seen) {
                if ($seen->location === $finding->location && $seen->title === $finding->title) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @param list<Finding> $findings
         */
        private function summarize(array $findings): string
        {
            $lines = [];
            foreach ($findings as $finding) {
                $lines[] = '- '.$finding->title.' ('.$finding->location.')';
            }

            return \implode(\PHP_EOL, $lines);
        }
    }

Two knobs govern the loop, mirroring how an adversarial auditor is usually tuned: ``maxIterations``
caps how many generate-review rounds run, and ``minConfidence`` sets the floor a candidate must clear
before it is worth a review call. The loop also stops early as soon as an iteration confirms nothing
new, so an easy target does not pay for three full rounds.

Step 5: Run the Audit
---------------------

Wire the two agents into the loop and run it against a file. Only reviewer-confirmed findings come
back::

    use App\Audit\ReviewLoop;

    $loop = new ReviewLoop($generator, $reviewer);

    $code = file_get_contents(__DIR__.'/src/Controller/CheckoutController.php');
    $confirmed = $loop->run($code);

    foreach ($confirmed as $finding) {
        printf('[%s] %s (%s)'.\PHP_EOL, strtoupper($finding->severity->value), $finding->title, $finding->location);
    }

Using the AI Bundle?
--------------------

The AI Bundle registers the structured-output subscriber for you, so ``response_format`` works out
of the box. Declare the two agents in configuration:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'
        agent:
            generator:
                model: 'gpt-4o-mini'
                prompt: 'You are a security auditor for Symfony applications. ...'
            reviewer:
                model: 'gpt-4o-mini'
                prompt: 'You are a skeptical security reviewer. ...'

Each agent is exposed as an ``ai.agent.<name>`` service. Inject the two into the loop by id:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Audit\ReviewLoop:
            arguments:
                $generator: '@ai.agent.generator'
                $reviewer: '@ai.agent.reviewer'

Best Practices
--------------

* **Separate the Roles**: Run the reviewer as its own agent with its own prompt so each finding is
  judged from a clean context, instead of letting the generator ratify what it just produced
* **Give the Reviewer a Skeptical Prompt**: Its value is rejecting weak findings, so instruct it to
  reject speculative or unreachable ones rather than to be helpful
* **Feed Verdicts Back**: Passing confirmed findings and the reason for each rejection into the next
  generator prompt is what turns a loop into refinement instead of the same candidates re-scored
  every round
* **Filter Before Reviewing**: A confidence floor keeps low-value candidates from spending a reviewer
  call — the review step is the expensive one
* **Use a Different Model to Review**: A separate agent on the same model still shares its blind
  spots; pointing the reviewer at a different (and often smaller, cheaper) model adds genuinely
  independent judgment as well as saving cost

Learn More
----------

* :doc:`multi-agent-orchestration` - Route questions to specialist agents instead of reviewing output
* :doc:`../components/agent` - Processors, memory, and advanced agent patterns
* :doc:`../components/platform` - Structured output and platform configuration reference
