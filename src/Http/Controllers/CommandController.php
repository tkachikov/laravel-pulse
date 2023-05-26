<?php
declare(strict_types=1);

namespace Tkachikov\LaravelCommands\Http\Controllers;

use Throwable;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tkachikov\LaravelCommands\Models\Command;
use Tkachikov\LaravelCommands\Models\Schedule;
use Tkachikov\LaravelCommands\Http\Requests\ScheduleRunRequest;
use Tkachikov\LaravelCommands\Http\Requests\ScheduleSaveRequest;
use Tkachikov\LaravelCommands\Models\CommandLog;
use Tkachikov\LaravelCommands\Models\CommandRun;
use Tkachikov\LaravelCommands\Jobs\CommandRunJob;
use Tkachikov\LaravelCommands\Services\ScheduleService;

class CommandController extends Controller
{
    public function __construct(
        public ScheduleService $service,
    ) {
    }

    public function index(Request $request)
    {
        $commands = $request->has('sortKey')
            ? $this->service->getSorted(...$request->only(['sortKey', 'sortBy']))
            : $this->service->getGroups();

        return view('commands::index', [
            'commands' => $commands,
            'times' => $this->service->getTimes(),
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $command = Command::find($id);
        $commandInfo = $this->service->getForClass($command->class);
        $viewData = [
            'Short Name' => $commandInfo['shortName'],
            'Name' => $commandInfo['name'],
            'Description' => $commandInfo['description'],
            'Class' => $commandInfo['class'],
            'Signature' => $commandInfo['signature']['full'],
        ];
        foreach (['schedule', 'manual'] as $word) {
            $prefix = 'runIn' . str($word)->studly()->toString();
            $method = $prefix . 'Html';
            $index = str($prefix)->headline()->lower()->ucfirst()->toString();
            $viewData[$index] = method_exists($commandInfo['object'], $method)
                ? $commandInfo['object']->$method()
                : '<span class="text-success">Yes</span>';
        }
        $schedule = $request->has('schedule')
            ? Schedule::findOrFail($request->integer('schedule'))
            : null;
        $runs = CommandRun::query()
            ->whereCommandId($id)
            ->orderByDesc('id')
            ->paginate(10);
        $logs = [];
        foreach ($runs as $run) {
            $logs[$run->id] = CommandLog::whereCommandRunId($run->id)->simplePaginate(5, pageName: "logs_{$run->id}");
        }

        return view('commands::edit', [
            'command' => $commandInfo,
            'data' => $viewData,
            'times' => $this->service->getTimes(),
            'schedule' => $schedule,
            'runs' => $runs,
            'logs' => $logs,
        ]);
    }

    public function update(string $command, ScheduleSaveRequest $request)
    {
        $this->service->saveSchedule($request->validated());

        return redirect()
            ->route('commands.edit', [
                'command' => $command,
                'schedule' => $request->get('id'),
            ]);
    }

    public function destroy(int $command, int $schedule)
    {
        Schedule::find($schedule)->delete();

        return redirect()->route('commands.edit', $command);
    }

    public function run(int $id, ScheduleRunRequest $request)
    {
        $commandInfo = $this->service->getForClass(Command::find($id)->class);
        try {
            if (method_exists($commandInfo['object'], 'runInManual') && !$commandInfo['object']->runInManual()) {
                throw new Exception('Not use in manual running');
            }
            dispatch(new CommandRunJob($commandInfo['class'], $request->get('args', [])));
            $out = ['type' => 'success', 'message' => 'Command added in queue'];
        } catch (Throwable $e) {
            $out = ['type' => 'error', 'message' => $e->getMessage()];
        }

        return redirect()
            ->route('commands.edit', $id)
            ->with(["{$out['type']}-message" => $out['message']]);
    }
}