        async function notifyTaskUpdate(taskId, action = 'update') {

    try {

        const response = await fetch('notify_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId,
                action: action
            })
        });

        return await response.json();

    } catch (err) {
        console.error('Notification error:', err);
    }
}