<?php  
session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) ) {
    header("Location: login.php"); // Redirect to login page
    exit();
}


$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'student';
$PhotoDir = '';
$defaultAvatar = 'img/avatars/avatar.jpg';


$sql = "SELECT photo FROM students WHERE user_id = ?"; 
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$imageSrc = $defaultAvatar;

if ($row && !empty($row['photo'])) {
    $PhotoPath = $PhotoDir . $row['photo'];
    if (file_exists($PhotoPath)) {
        $imageSrc = $PhotoPath;
    }
}


$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// --- Fetch Groups for the Current User ---
$groups_list = [];
if ($current_user_role === 'student') {
    $sql = "SELECT g.id, g.name, t.id as teacher_id, t_user.name as teacher_name
            FROM message_groups g
            JOIN group_members gm ON g.id = gm.group_id
            JOIN students s ON gm.student_id = s.id
            JOIN teachers t ON g.teacher_id = t.id
            JOIN users t_user ON t.user_id = t_user.id
            WHERE s.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
} elseif ($current_user_role === 'teacher') { 
    $sql = "SELECT g.id, g.name, t.id as teacher_id, u.name as teacher_name
            FROM message_groups g
            JOIN teachers t ON g.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE t.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
} else { // Admin sees all groups
    $sql = "SELECT g.id, g.name, t.id as teacher_id, u.name as teacher_name
            FROM message_groups g
            JOIN teachers t ON g.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            ORDER BY g.name ASC";
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $groups_list[] = $row;
}
$stmt->close();

// --- Fetch Conversation and Verify Membership if a Group is Selected ---
$messages = [];
$current_group_info = null;
$can_manage = false;
if ($group_id > 0) {
    foreach ($groups_list as $group) {
        if ($group['id'] == $group_id) {
            $current_group_info = $group;
            // Check if current user is the managing teacher
            $teacher_check_sql = "SELECT EXISTS(SELECT 1 FROM teachers WHERE id = ? AND user_id = ?) as is_manager";
            $stmt = $conn->prepare($teacher_check_sql);
            $stmt->bind_param("ii", $group['teacher_id'], $current_user_id);
            $stmt->execute();
            $is_manager = $stmt->get_result()->fetch_assoc()['is_manager'];
            $stmt->close();
            
            if($current_user_role === 'admin' || $is_manager) {
                $can_manage = true;
            }
            break;
        }
    }

    if ($current_group_info) {
        $messages_sql = "
            SELECT m.id, m.sender_id, u.name as sender_name, u.role as sender_role, m.message, m.file_path, m.original_file_name, m.created_at
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.group_id = ?
            ORDER BY m.created_at ASC
        ";
        $stmt = $conn->prepare($messages_sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $messages_result = $stmt->get_result();
        while ($msg_row = $messages_result->fetch_assoc()) {
            $messages[] = $msg_row;
        }
        $stmt->close();
    } else {
        $group_id = 0;
    }
}
?>
<?php include "dashboard-top.php"; ?>
<style>
    .chat-app { display: flex; height: 80vh; }
    .chat-list { width: 30%; border-right: 1px solid #dee2e6; display: flex; flex-direction: column; }
    .chat-list-body { overflow-y: auto; flex-grow: 1; }
    .chat-list .list-group-item.active { background-color: #0d6efd; color: white; }
    .chat-window { width: 70%; display: flex; flex-direction: column; }
    .chat-header { padding: 1rem; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; }
    .chat-messages { flex-grow: 1; padding: 1rem; overflow-y: auto; background-color: #f5f7fb; }
    .message { margin-bottom: 1rem; display: flex; flex-direction: column; }
    .message-header { font-size: 0.8rem; font-weight: bold; margin-bottom: 0.25rem; }
    .message.sent { align-items: flex-end; }
    .message.received { align-items: flex-start; }
    .message-bubble { padding: 0.5rem 1rem; border-radius: 1rem; max-width: 60%; }
    .message.sent .message-bubble { background-color: #007bff; color: white; }
    .message.received .message-bubble { background-color: #fff; border: 1px solid #dee2e6; }
    .message-time { font-size: 0.75rem; color: #6c757d; margin-top: 0.25rem; }
    .chat-input { padding: 1rem; border-top: 1px solid #dee2e6; background-color: #f8f9fa; }
    .file-attachment a { text-decoration: none; color: inherit; }
    .file-attachment a:hover { text-decoration: underline; }
    .file-attachment .feather { width: 18px; height: 18px; vertical-align: middle; margin-right: 4px; }
    #file-upload-btn { cursor: pointer; }
    #file-preview { margin-top: 10px; font-style: italic; color: #6c757d; }
</style>

<?php 
    if ($current_user_role === 'student') {
        include "sidebar_student.php";
    } elseif ($current_user_role === 'teacher') {
        include "sidebar_teacher.php";
    } else {
        include "sidebar_ad.php";
    }
?>
<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3">Group Messages</h1>
            <?php if ($current_user_role === 'admin'): ?>
                <a href="create_group.php" class="btn btn-primary">Create New Group</a>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="row g-0">
                <div class="chat-app">
                    <!-- Left Pane: Group List -->
                    <div class="chat-list">
                        <div class="chat-list-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($groups_list as $group): ?>
                                    <a href="?group_id=<?php echo $group['id']; ?>" class="list-group-item list-group-item-action <?php if ($group_id === $group['id']) echo 'active'; ?>">
                                        <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                                        <div class="small text-muted">Managed by: <?php echo htmlspecialchars($group['teacher_name']); ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Pane: Chat Window -->
                    <div class="chat-window">
                        <?php if ($group_id > 0 && $current_group_info): ?>
                            <div class="chat-header d-flex justify-content-between align-items-center">
                                <strong><?php echo htmlspecialchars($current_group_info['name']); ?></strong>
                                <?php if ($can_manage): ?>
                                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#manageGroupModal">
                                        <i data-feather="settings"></i> Manage Group
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="chat-messages" id="chat-messages">
                                <?php foreach ($messages as $msg): ?>
                                    <div class="message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['id']; ?>">
                                        <?php if ($msg['sender_id'] != $current_user_id): ?><div class="message-header"><?php echo htmlspecialchars($msg['sender_name']); ?></div><?php endif; ?>
                                        <div class="message-bubble">
                                            <?php if(!empty($msg['message'])): ?><p class="mb-0"><?php echo htmlspecialchars($msg['message']); ?></p><?php endif; ?>
                                            <?php if(!empty($msg['file_path'])): ?>
                                                 <div class="file-attachment mt-2"><a href="../uploads/group_messages/<?php echo htmlspecialchars($msg['file_path']); ?>" download="<?php echo htmlspecialchars($msg['original_file_name']); ?>"><i data-feather="download"></i> <?php echo htmlspecialchars($msg['original_file_name']); ?></a></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-time"><?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="chat-input">
                                <form id="message-form" class="d-flex align-items-center">
                                    <input type="text" name="message" class="form-control" placeholder="Type a message..." autocomplete="off"><label for="file-upload" class="btn btn-light ms-2"><i data-feather="paperclip"></i></label><input type="file" name="file" id="file-upload" class="d-none"><button type="submit" class="btn btn-primary ms-2">Send</button>
                                </form>
                                <div id="file-preview"></div> 
                            </div>
                        <?php else: ?>
                            <div class="d-flex h-100 justify-content-center align-items-center"><div class="text-center"><i class="fs-1" data-feather="message-square"></i><h4 class="mt-2">Select a group</h4><p class="text-muted">Choose a group from the left to see messages.</p></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Manage Group Modal -->
<div class="modal fade" id="manageGroupModal" tabindex="-1" aria-labelledby="manageGroupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageGroupModalLabel">Manage: <?php echo htmlspecialchars($current_group_info['name'] ?? ''); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs" id="manageGroupTabs" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">Members</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="add-member-tab" data-bs-toggle="tab" data-bs-target="#add-member" type="button" role="tab">Add Member</button></li>
          <?php if ($current_user_role === 'admin'): ?>
          <li class="nav-item" role="presentation"><button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Settings</button></li>
          <?php endif; ?>
        </ul>
        <div class="tab-content" id="manageGroupTabsContent">
          <div class="tab-pane fade show active p-3" id="members" role="tabpanel">
            <h6>Current Members</h6>
            <ul class="list-group" id="group-member-list">
              <!-- Member list will be loaded via AJAX -->
            </ul>
          </div>
          <div class="tab-pane fade p-3" id="add-member" role="tabpanel">
             <h6>Add New Member</h6>
             <div class="input-group">
                <select class="form-select" id="add-student-select">
                    <!-- Student list will be loaded via AJAX -->
                </select>
                <button class="btn btn-outline-primary" type="button" id="add-member-btn">Add</button>
            </div>
          </div>
          <?php if ($current_user_role === 'admin'): ?>
          <div class="tab-pane fade p-3" id="settings" role="tabpanel">
            <h6>Delete Group</h6>
            <p class="text-danger">This action is permanent and cannot be undone. It will delete the group and all associated messages.</p>
            <button class="btn btn-danger" id="delete-group-btn">Delete This Group</button>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    feather.replace();

    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
        const groupId = <?php echo $group_id; ?>;
        if (groupId > 0) {
            const messageForm = document.getElementById('message-form');
            const messageInput = messageForm.querySelector('input[name="message"]');
            const fileInput = document.getElementById('file-upload');
            const filePreview = document.getElementById('file-preview');

            fileInput.addEventListener('change', () => filePreview.textContent = fileInput.files.length > 0 ? `File: ${fileInput.files[0].name}` : '');

            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const messageText = messageInput.value.trim();
                const file = fileInput.files[0];
                if (messageText === '' && !file) return;

                const formData = new FormData();
                formData.append('message', messageText);
                formData.append('group_id', groupId);
                if(file) formData.append('file', file);

                messageInput.value = '';
                fileInput.value = '';
                filePreview.textContent = '';

                fetch('send_group_message.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => { if (data.status !== 'success') console.error('Message failed to send.'); })
                .catch(error => console.error('Error sending message:', error));
            });
            setInterval(fetchNewMessages, 3000);
        }
    }

    function fetchNewMessages() {
        const groupId = <?php echo $group_id; ?>;
        const chatMessages = document.getElementById('chat-messages');
        const lastMessage = chatMessages.querySelector('.message:last-child');
        const lastMessageId = lastMessage ? lastMessage.dataset.messageId : 0;

        fetch(`fetch_group_messages.php?group_id=${groupId}&last_id=${lastMessageId}`)
        .then(res => res.json())
        .then(newMessages => {
            if (newMessages.length > 0) {
                newMessages.forEach(msg => appendMessage(msg));
                if (chatMessages.scrollHeight - chatMessages.scrollTop < chatMessages.clientHeight + 100) {
                     chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }
        })
        .catch(error => console.error('Error fetching messages:', error));
    }

    function appendMessage(msg) {
        const chatMessages = document.getElementById('chat-messages');
        const currentUserId = <?php echo $current_user_id; ?>;
        if (document.querySelector(`.message[data-message-id='${msg.id}']`)) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${msg.sender_id == currentUserId ? 'sent' : 'received'}`;
        messageDiv.dataset.messageId = msg.id;

        let senderHeader = '';
        if (msg.sender_id != currentUserId) {
            senderHeader = `<div class="message-header">${escapeHTML(msg.sender_name)}</div>`;
        }

        let msgText = msg.message ? `<p class="mb-0">${escapeHTML(msg.message)}</p>` : '';
        let fileText = '';
        if(msg.file_path) {
            fileText = `<div class="file-attachment mt-2"><a href="../uploads/group_messages/${escapeHTML(msg.file_path)}" download="${escapeHTML(msg.original_file_name)}"><i data-feather="download"></i> ${escapeHTML(msg.original_file_name)}</a></div>`;
        }

        const date = new Date(msg.created_at.replace(' ', 'T') + 'Z');
        const timeText = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric'}) + ', ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        messageDiv.innerHTML = `${senderHeader}<div class="message-bubble">${msgText}${fileText}</div><div class="message-time">${timeText}</div>`;
        
        chatMessages.appendChild(messageDiv);
        feather.replace();
    }
    
    const manageModal = document.getElementById('manageGroupModal');
    if (manageModal) {
        manageModal.addEventListener('show.bs.modal', function () {
            loadManagementData();
        });
    }

    function loadManagementData() {
        const groupId = <?php echo $group_id; ?>;
        if (!groupId) return;
        fetch(`ajax_manage_group.php?action=get_members&group_id=${groupId}`)
        .then(res => res.json())
        .then(data => {
            const memberList = document.getElementById('group-member-list');
            const studentSelect = document.getElementById('add-student-select');
            memberList.innerHTML = '';
            studentSelect.innerHTML = '<option selected disabled>Choose a student...</option>';

            if (data.status === 'success') {
                data.members.forEach(member => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    li.innerHTML = `${escapeHTML(member.name)} <button class="btn btn-sm btn-outline-danger remove-member-btn" data-student-id="${member.student_id}">Remove</button>`;
                    memberList.appendChild(li);
                });

                data.non_members.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.student_id;
                    option.textContent = escapeHTML(student.name);
                    studentSelect.appendChild(option);
                });
            }
        })
        .catch(err => console.error("Failed to load management data:", err));
    }

    document.body.addEventListener('click', function(e) {
        if (e.target.matches('.remove-member-btn')) {
            const studentId = e.target.dataset.studentId;
            manageMember('remove_member', studentId);
        }
        if (e.target.id === 'add-member-btn') {
            const studentId = document.getElementById('add-student-select').value;
            if (studentId) manageMember('add_member', studentId);
        }
        if (e.target.id === 'delete-group-btn') {
            if (confirm('Are you sure you want to permanently delete this group?')) {
                manageMember('delete_group', null);
            }
        }
    });

    function manageMember(action, studentId) {
        const groupId = <?php echo $group_id; ?>;
        const formData = new FormData();
        formData.append('action', action);
        formData.append('group_id', groupId);
        if (studentId) formData.append('student_id', studentId);

        fetch('ajax_manage_group.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if(action === 'delete_group') {
                    alert('Group deleted successfully.');
                    window.location.href = 'messages_group.php';
                } else {
                    loadManagementData(); // Refresh the lists
                }
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(err => console.error("Management action failed:", err));
    }

    function escapeHTML(str) {
        var p = document.createElement('p');
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }
});
</script>

<?php include "footer.php"; ?>
</body>

</html>