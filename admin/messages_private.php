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

$current_user_role = $_SESSION['role'];
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
$current_user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Student';
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$view = isset($_GET['view']) ? $_GET['view'] : 'recent'; // Default view is 'recent'

// --- Fetch User's Profile Photo ---
$PhotoDir = '';
$defaultAvatar = 'img/avatars/avatar.jpg';
$imageSrc = $defaultAvatar;

$photo_sql = "SELECT photo FROM students WHERE user_id = ?";
$stmt = $conn->prepare($photo_sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (!empty($row['photo']) && file_exists($PhotoDir . $row['photo'])) {
        $imageSrc = $PhotoDir . $row['photo'];
    }
}
$stmt->close();

// --- Function to hydrate user data with photos ---
function hydrate_user_list($result, $defaultAvatar, $PhotoDir) {
    $list = [];
    while ($user_row = $result->fetch_assoc()) {
        $user_photo = $defaultAvatar;
        if ($user_row['role'] === 'student' && !empty($user_row['student_photo']) && file_exists($PhotoDir . $user_row['student_photo'])) {
            $user_photo = $PhotoDir . $user_row['student_photo'];
        } elseif ($user_row['role'] === 'teacher' && !empty($user_row['teacher_photo']) && file_exists($PhotoDir . $user_row['teacher_photo'])) {
            $user_photo = $PhotoDir . $user_row['teacher_photo'];
        }
        $list[] = [
            'id' => $user_row['id'],
            'name' => $user_row['name'],
            'role' => $user_row['role'],
            'photo' => $user_photo
        ];
    }
    return $list;
}

// --- Fetch Recent Chats ---
$recent_chats_list = [];
$recent_sql = "
    SELECT u.id, u.name, u.role, s.photo AS student_photo, t.photo AS teacher_photo, MAX(pm.sent_at) as last_message_time
    FROM private_messages pm
    JOIN users u ON u.id = IF(pm.sender_id = ?, pm.receiver_id, pm.sender_id)
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE (pm.sender_id = ? OR pm.receiver_id = ?) AND u.id != ?
    GROUP BY u.id, u.name, u.role, s.photo, t.photo
    ORDER BY last_message_time DESC
";
$stmt = $conn->prepare($recent_sql);
$stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$recent_result = $stmt->get_result();
$recent_chats_list = hydrate_user_list($recent_result, $defaultAvatar, $PhotoDir);
$stmt->close();


// --- Fetch All Users (Students and Teachers) ---
$all_users_list = [];
$users_sql = "
    SELECT u.id, u.name, u.role, s.photo AS student_photo, t.photo AS teacher_photo
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id AND u.role = 'student'
    LEFT JOIN teachers t ON u.id = t.user_id AND u.role = 'teacher'
    WHERE u.id != ? AND u.role IN ('student', 'teacher')
    ORDER BY u.name ASC
";
$stmt = $conn->prepare($users_sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$users_result = $stmt->get_result();
$all_users_list = hydrate_user_list($users_result, $defaultAvatar, $PhotoDir);
$stmt->close();

$users_to_display = ($view === 'all') ? $all_users_list : $recent_chats_list;

// --- Fetch Conversation if a Receiver is Selected ---
$messages = [];
$receiver_info = null;
if ($receiver_id > 0) {
    foreach ($all_users_list as $user) { // Check against all users to get info
        if ($user['id'] == $receiver_id) {
            $receiver_info = $user;
            break;
        }
    }

    $messages_sql = "
        SELECT id, sender_id, message, file_path, original_file_name, sent_at
        FROM private_messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY sent_at ASC
    ";
    $stmt = $conn->prepare($messages_sql);
    $stmt->bind_param("iiii", $current_user_id, $receiver_id, $receiver_id, $current_user_id);
    $stmt->execute();
    $messages_result = $stmt->get_result();
    while ($msg_row = $messages_result->fetch_assoc()) {
        $messages[] = $msg_row;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php"; ?>
<style>
    .chat-app { display: flex; height: 80vh; }
    .chat-list { width: 30%; border-right: 1px solid #dee2e6; display: flex; flex-direction: column; }
    .chat-list-search { padding: 0.75rem; border-bottom: 1px solid #dee2e6; }
    .chat-list-tabs { padding: 0.5rem; border-bottom: 1px solid #dee2e6; text-align: center; }
    .chat-list-body { overflow-y: auto; flex-grow: 1; }
    .chat-list .list-group-item { cursor: pointer; }
    .chat-list .list-group-item.active { background-color: #0d6efd; color: white; }
    .chat-window { width: 70%; display: flex; flex-direction: column; }
    .chat-header { padding: 1rem; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; }
    .chat-messages { flex-grow: 1; padding: 1rem; overflow-y: auto; background-color: #e9ebee; }
    .message { margin-bottom: 1rem; display: flex; flex-direction: column; }
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
        include "sidebar_ad.php"; // Assuming admin sidebar is in the admin folder
    }
?>
<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Private Messages</h1>
        <div class="card">
            <div class="row g-0">
                <div class="chat-app">
                    <!-- Left Pane: User List -->
                    <div class="chat-list">
                        <div class="chat-list-search">
                            <input type="text" class="form-control" placeholder="Search contacts..." id="chat-search-input">
                        </div>
                        <div class="chat-list-tabs nav nav-pills" id="v-pills-tab" role="tablist">
                            <a class="nav-link flex-fill <?php if($view === 'recent') echo 'active'; ?>" href="?view=recent">Recent</a>
                            <a class="nav-link flex-fill <?php if($view === 'all') echo 'active'; ?>" href="?view=all">All Contacts</a>
                        </div>
                        <div class="chat-list-body">
                            <div class="list-group list-group-flush" id="user-list-container">
                                <?php if (empty($users_to_display)): ?>
                                    <div class="text-center text-muted p-4">
                                        <?php if ($view === 'all'): ?>
                                            No other users found.
                                        <?php else: ?>
                                            No recent conversations. <br><a href="?view=all">Start a new chat!</a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($users_to_display as $user): ?>
                                        <a href="?view=<?php echo $view; ?>&receiver_id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action <?php if ($receiver_id === $user['id']) echo 'active'; ?>">
                                            <div class="d-flex align-items-start">
                                                <img src="<?php echo htmlspecialchars($user['photo']); ?>" class="rounded-circle me-3" alt="<?php echo htmlspecialchars($user['name']); ?>" width="40" height="40">
                                                <div class="flex-grow-1">
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <div class="small"><span class="badge bg-secondary"><?php echo ucfirst($user['role']); ?></span></div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Pane: Chat Window -->
                    <div class="chat-window">
                        <?php if ($receiver_id > 0 && $receiver_info): ?>
                            <div class="chat-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($receiver_info['photo']); ?>" class="rounded-circle me-3" alt="<?php echo htmlspecialchars($receiver_info['name']); ?>" width="40" height="40">
                                    <div>
                                        <strong><?php echo htmlspecialchars($receiver_info['name']); ?></strong>
                                        <div class="text-muted small"><em><?php echo ucfirst($receiver_info['role']); ?></em></div>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-messages" id="chat-messages">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center text-muted mt-3">Start the conversation!</div>
                                <?php else: ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['id']; ?>">
                                            <div class="message-bubble">
                                                <?php if(!empty($msg['message'])): ?>
                                                    <p class="mb-0"><?php echo htmlspecialchars($msg['message']); ?></p>
                                                <?php endif; ?>
                                                <?php if(!empty($msg['file_path'])): ?>
                                                     <div class="file-attachment mt-2">
                                                        <a href="../uploads/personal_massages/<?php echo htmlspecialchars($msg['file_path']); ?>" download="<?php echo htmlspecialchars($msg['original_file_name']); ?>">
                                                            <i data-feather="download"></i> <?php echo htmlspecialchars($msg['original_file_name']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-time">
                                                <?php echo date('M d, g:i A', strtotime($msg['sent_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="chat-input">
                                <form id="message-form" class="d-flex align-items-center">
                                    <input type="text" name="message" class="form-control" placeholder="Type your message" autocomplete="off">
                                    <label for="file-upload" class="btn btn-light ms-2" id="file-upload-btn"><i data-feather="paperclip"></i></label>
                                    <input type="file" name="file" id="file-upload" class="d-none">
                                    <button type="submit" class="btn btn-primary ms-2">Send</button>
                                </form>
                                <div id="file-preview"></div>
                            </div>
                        <?php else: ?>
                            <div class="d-flex h-100 justify-content-center align-items-center">
                                <div class="text-center">
                                    <i class="fs-1 bi bi-chat-dots"></i>
                                    <h4 class="mt-2">Select a conversation</h4>
                                    <p class="text-muted">Choose someone from the left panel to start chatting.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Feather Icons -->
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    feather.replace();

    const searchInput = document.getElementById('chat-search-input');
    const userListContainer = document.getElementById('user-list-container');
    const userItems = userListContainer.getElementsByTagName('a');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        for (let i = 0; i < userItems.length; i++) {
            const userName = userItems[i].getElementsByTagName('strong')[0];
            if (userName) {
                if (userName.innerHTML.toLowerCase().indexOf(filter) > -1) {
                    userItems[i].style.display = "";
                } else {
                    userItems[i].style.display = "none";
                }
            }
        }
    });

    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
        const receiverId = <?php echo $receiver_id; ?>;
        if (receiverId > 0) {
            const messageForm = document.getElementById('message-form');
            const messageInput = messageForm.querySelector('input[name="message"]');
            const fileInput = document.getElementById('file-upload');
            const filePreview = document.getElementById('file-preview');

            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    filePreview.textContent = `Selected file: ${fileInput.files[0].name}`;
                } else {
                    filePreview.textContent = '';
                }
            });

            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const messageText = messageInput.value.trim();
                const file = fileInput.files[0];
                if (messageText === '' && !file) return;

                const formData = new FormData();
                formData.append('message', messageText);
                formData.append('receiver_id', receiverId);
                if(file) {
                    formData.append('file', file);
                }

                messageInput.value = '';
                fileInput.value = '';
                filePreview.textContent = '';

                fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        console.error('Message failed to send.');
                        messageInput.value = messageText;
                    }
                })
                .catch(error => console.error('Error sending message:', error));
            });

            setInterval(fetchNewMessages, 3000);
        }
    }

    function fetchNewMessages() {
        const receiverId = <?php echo $receiver_id; ?>;
        const chatMessages = document.getElementById('chat-messages');
        const lastMessage = chatMessages.querySelector('.message:last-child');
        const lastMessageId = lastMessage ? lastMessage.dataset.messageId : 0;

        fetch(`fetch_messages.php?receiver_id=${receiverId}&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(newMessages => {
                if (newMessages.length > 0) {
                    newMessages.forEach(msg => {
                        appendMessage(msg);
                    });
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
        if (document.querySelector(`.message[data-message-id='${msg.id}']`)) {
            return;
        }

        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.dataset.messageId = msg.id;

        const bubbleDiv = document.createElement('div');
        bubbleDiv.classList.add('message-bubble');
        
        if(msg.message) {
            const p = document.createElement('p');
            p.classList.add('mb-0');
            p.textContent = msg.message;
            bubbleDiv.appendChild(p);
        }

        if(msg.file_path) {
            const fileDiv = document.createElement('div');
            fileDiv.classList.add('file-attachment', 'mt-2');
            const link = document.createElement('a');
            link.href = `../uploads/personal_massages/${msg.file_path}`;
            link.download = msg.original_file_name;
            const iconSvg = feather.icons.download.toSvg();
            link.innerHTML = iconSvg + ` ${msg.original_file_name}`;
            fileDiv.appendChild(link);
            bubbleDiv.appendChild(fileDiv);
        }

        const timeDiv = document.createElement('div');
        timeDiv.classList.add('message-time');
        const date = new Date(msg.sent_at.replace(' ', 'T') + 'Z');
        timeDiv.textContent = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric'}) + ', ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        if (msg.sender_id == currentUserId) {
            messageDiv.classList.add('sent');
        } else {
            messageDiv.classList.add('received');
        }
        
        const placeholder = chatMessages.querySelector('.text-center.text-muted');
        if (placeholder) {
            placeholder.remove();
        }

        messageDiv.appendChild(bubbleDiv);
        messageDiv.appendChild(timeDiv);
        chatMessages.appendChild(messageDiv);
        feather.replace();
    }
});
</script>

<?php include "footer.php"; ?>
</body>

</html>