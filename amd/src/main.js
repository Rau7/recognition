/**
 * Recognition wall JavaScript.
 *
 * @module     local_recognition/main
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/notification"], function ($, Notification) {
  return {
    init: function () {
      // --- Mention/autocomplete ---
      $(document).on("input", ".mention-textarea", function () {
        const $textarea = $(this);
        const cursorPos = $textarea.prop("selectionStart");
        const text = $textarea.val();
        const lastAt = text.lastIndexOf("@", cursorPos - 1);

        if (
          lastAt >= 0 &&
          (lastAt === 0 || /\s/.test(text.charAt(lastAt - 1)))
        ) {
          const query = text.substring(lastAt + 1, cursorPos);

          if (query.length >= 1) {
            $.ajax({
              url: M.cfg.wwwroot + "/local/recognition/ajax.php",
              method: "GET",
              data: {
                action: "searchusers",
                query: query,
                sesskey: M.cfg.sesskey,
              },
              dataType: "json",
              success: function (res) {
                if (res.success) {
                  let $dropdown = $("#mention-dropdown");
                  if ($dropdown.length === 0) {
                    $dropdown = $(
                      '<ul id="mention-dropdown" class="mention-dropdown"></ul>'
                    ).appendTo("body");
                  }

                  $dropdown.empty();
                  (res.data || []).forEach(function (user) {
                    const $item = $('<li class="mention-item"></li>')
                      .text(user.fullname)
                      .attr("data-uid", user.id);
                    $dropdown.append($item);
                  });

                  const offset = $textarea.offset();
                  $dropdown.css({
                    top: offset.top + $textarea.outerHeight(),
                    left: offset.left,
                    width: $textarea.outerWidth(),
                    position: "absolute",
                    zIndex: 9999,
                    display: "block",
                  });
                } else {
                  $("#mention-dropdown").hide();
                }
              },
              error: function () {
                $("#mention-dropdown").hide();
              },
            });
          } else {
            $("#mention-dropdown").hide();
          }
        } else {
          $("#mention-dropdown").hide();
        }
      });

      $(document).on("mousedown", ".mention-item", function (e) {
        e.preventDefault();
        const $item = $(this);
        const $textarea = $(".mention-textarea:focus");
        if ($textarea.length === 0) return;

        const cursorPos = $textarea.prop("selectionStart");
        const text = $textarea.val();
        const lastAt = text.lastIndexOf("@", cursorPos - 1);

        const before = text.substring(0, lastAt);
        const after = text.substring(cursorPos);
        const mentionText = "@" + $item.text() + " ";

        $textarea.val(before + mentionText + after);
        $textarea.focus();
        $textarea[0].setSelectionRange(
          (before + mentionText).length,
          (before + mentionText).length
        );
        $("#mention-dropdown").hide();
      });

      $(document).on("blur", ".mention-textarea", function () {
        setTimeout(function () {
          $("#mention-dropdown").hide();
        }, 200);
      });

      // --- File upload preview ---
      $(".file-input").on("change", function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const maxSize = $(this).data("max-size");
        if (file.size > maxSize) {
          Notification.addNotification({
            message: "File size must be less than 5MB",
            type: "error",
          });
          $(this).val("");
          return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
          const $preview = $(".post-attachments");
          $preview
            .html(
              `
            <div class="attachment-preview">
              <img src="${e.target.result}" alt="Preview">
              <div class="attachment-remove">&times;</div>
            </div>`
            )
            .show();
        };
        reader.readAsDataURL(file);
      });

      $(document).on("click", ".attachment-remove", function () {
        $(".file-input").val("");
        $(".post-attachments").empty().hide();
      });

      // --- Like, Thanks, Celebration buttons ---
      function handleReaction(className, actionName, countKey, toggleClass) {
        $(document).on("click", className, function (e) {
          e.preventDefault();
          const btn = $(this);
          const recordId = btn.data("record-id");

          $.ajax({
            url: M.cfg.wwwroot + "/local/recognition/ajax.php",
            type: "POST",
            data: {
              action: actionName,
              postid: recordId,
              content: "",
              commentid: 0,
              sesskey: M.cfg.sesskey,
            },
            dataType: "json",
            success: function (response) {
              if (response.success) {
                btn.find(`.${countKey}-count`).text(response.data[countKey]);
                btn.toggleClass(
                  toggleClass,
                  response.data["is" + capitalize(countKey)]
                );
              } else {
                Notification.addNotification({
                  message: response.message || `Error performing ${actionName}`,
                  type: "error",
                });
              }
            },
            error: function (xhr, status, error) {
              Notification.addNotification({
                message: "Error connecting to server: " + error,
                type: "error",
              });
            },
          });
        });
      }

      function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
      }

      handleReaction(".recognition-like-btn", "like", "likes", "liked");
      handleReaction(".recognition-thanks-btn", "thanks", "thanks", "thanked");
      handleReaction(
        ".recognition-celebration-btn",
        "celebration",
        "celebration",
        "celebrated"
      );

      // --- Comments ---
      $(document).on("click", ".recognition-comments-btn", function (e) {
        e.preventDefault();
        const btn = $(this);
        const recordId = btn.data("record-id");
        const commentsSection = $("#comments-" + recordId);

        commentsSection.slideToggle();

        if (commentsSection.find(".comments-list").length === 0) {
          //commentsSection.prepend('<div class="comments-list"></div>');
          loadComments(recordId, commentsSection, btn);
        }
      });

      $(document).on("submit", ".recognition-comment-form", function (e) {
        e.preventDefault();
        const form = $(this);
        const recordId = form.data("record-id");
        const input = form.find(".comment-input");
        const content = input.html().trim();

        form.find(".comment-hidden-content").val(content);

        const post = form.closest(".recognition-post");
        const commentsBtn = post.find(".recognition-comments-btn");
        const commentsSection = form.closest(".recognition-comments");

        if (!content) return;

        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "addcomment",
            postid: recordId,
            content: content,
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              input.html("");
              loadComments(recordId, commentsSection, commentsBtn);
            } else {
              Notification.addNotification({
                message: response.message || "Error adding comment",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      });

      function loadComments(recordId, commentsSection, btn) {
        $.ajax({
          url: M.cfg.wwwroot + "/local/recognition/ajax.php",
          type: "POST",
          data: {
            action: "getcomments",
            postid: recordId,
            content: "",
            commentid: 0,
            sesskey: M.cfg.sesskey,
          },
          dataType: "json",
          success: function (response) {
            if (response.success) {
              commentsSection.find(".comments-list").html(response.data.html);
              btn.find(".comments-count").text(response.data.count);
            } else {
              Notification.addNotification({
                message: response.message || "Error loading comments",
                type: "error",
              });
            }
          },
          error: function (xhr, status, error) {
            Notification.addNotification({
              message: "Error connecting to server: " + error,
              type: "error",
            });
          },
        });
      }

      // --- Pagination ---
      $(document).on(
        "click",
        "#recognition-pagination .page-item:not(.active) .page-link",
        function (e) {
          e.preventDefault();
          const pageUrl = $(this).attr("href");
          if (!pageUrl) return;

          const pageMatch = pageUrl.match(/[?&]page=(\d+)/);
          const page = pageMatch ? parseInt(pageMatch[1]) : 0;

          loadPostsPage(page);
        }
      );

      function loadPostsPage(page) {
        $(".loading-indicator").show();

        const baseUrl = window.location.href.split("?")[0];
        const newUrl = baseUrl + (page > 0 ? "?page=" + page : "");
        window.history.pushState({ page: page }, "", newUrl);

        $.ajax({
          url: baseUrl,
          data: {
            page: page,
            ajax: 1,
          },
          method: "GET",
          success: function (response) {
            const postsHtml = $(response)
              .find(".recognition-posts-container")
              .html();
            $(".recognition-posts-container").html(postsHtml);

            $("#recognition-pagination .page-item").removeClass("active");

            const totalPages = parseInt(
              $("#recognition-pagination").data("total-pages") || 0
            );
            if (totalPages > 0) {
              const lastPageUrl = baseUrl + "?page=" + (totalPages - 1);
              $("#recognition-pagination .page-item.last .page-link").attr(
                "href",
                lastPageUrl
              );
            }

            $("#recognition-pagination .page-item .page-link").each(
              function () {
                const linkUrl = $(this).attr("href");
                if (linkUrl) {
                  const linkPageMatch = linkUrl.match(/[?&]page=(\d+)/);
                  const linkPage = linkPageMatch
                    ? parseInt(linkPageMatch[1])
                    : 0;
                  if (linkPage === page) {
                    $(this).parent(".page-item").addClass("active");
                  }
                } else if (page === 0 && $(this).text().trim() === "1") {
                  $(this).parent(".page-item").addClass("active");
                }
              }
            );

            $(".loading-indicator").hide();
            $("html, body").animate(
              {
                scrollTop: $(".recognition-posts-container").offset().top - 100,
              },
              500
            );
          },
          error: function (xhr, status, error) {
            Notification.addNotification({
              message: "Error loading posts: " + error,
              type: "error",
            });
            $(".loading-indicator").hide();
          },
        });
      }
    },
  };
});
