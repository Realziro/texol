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


async function notifyJobCardUpdate(jobCardId, action = 'update') {

    try {

        const response = await fetch('notify_job.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                job_card_id: jobCardId,
                action: action
            })
        });

        return await response.json();

    } catch (err) {
        console.error('Job notification error:', err);
    }
}
async function notifyTaskNote(taskId, notes, action = 'note') {

    try {

        console.log('Sending task note:', { taskId, notes, action });

        const response = await fetch('notify_task_notes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId,
                action: action,
                notes: notes
            })
        });

        const result = await response.json();
        console.log('Task note response:', result);

        return result;

    } catch (err) {
        console.error('Task note notification error:', err);
    }
}

async function notifyJobNote(jobId, notes, action = 'note') {

    try {

        console.log('Sending job note:', { jobId, notes, action });

        const response = await fetch('notify_job_notes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                job_id: jobId,
                action: action,
                notes: notes
            })
        });

        const result = await response.json();

        console.log('Job note response:', result);

        return result;

    } catch (err) {

        console.error('Job note notification error:', err);
    }
}
async function notifyTicketClose(ticketId, action = 'update') {

    try {

        console.log('Sending ticket close notification:', { ticketId, action });

        const response = await fetch('notify_ticket_close.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ticket_id: ticketId,
                action: action
            })
        });

        const text = await response.text();

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON from server:', text);
            throw new Error('Server did not return valid JSON');
        }

        console.log('Ticket close response:', result);

        return result;

    } catch (err) {
        console.error('Ticket close notification error:', err);
    }
}