// Tribute.js mention entegrasyonu
// AJAX ile kullanıcı arama örneği de eklenmiştir.

document.addEventListener("DOMContentLoaded", function () {
  var tribute = new Tribute({
    values: function (text, cb) {
      // AJAX ile kullanıcı arama
      fetch(M.cfg.wwwroot + "/local/recognition/ajax.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:
          "action=searchusers&query=" +
          encodeURIComponent(text) +
          "&sesskey=" +
          encodeURIComponent(M.cfg.sesskey),
      })
        .then(function (resp) {
          return resp.json();
        })
        .then(function (data) {
          if (data.success) {
            cb(
              data.data.map(function (u) {
                return { key: u.fullname, value: u.id };
              })
            );
          } else {
            cb([]);
          }
        });
    },
    selectTemplate: function (item) {
      return (
        '<span class="mention-highlight" data-userid="' +
        item.original.value +
        '">@' +
        item.original.key +
        "</span>&nbsp;"
      );
    },
    menuItemTemplate: function (item) {
      return "<span>@" + item.string + "</span>";
    },
  });
  var editor = document.getElementById("mention-editor");
  if (editor) tribute.attach(editor);

  // Form submitte içeriği gizli inputa aktar
  var form = editor.closest("form");
  if (form) {
    form.addEventListener("submit", function () {
      document.getElementById("message-hidden").value = editor.innerHTML;
    });
  }
});
