import { Controller } from "@hotwired/stimulus";
import { getComponent } from "@symfony/ux-live-component";

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);
        this.scrollToBottom();

        this.component.on("loading.state:started", (event, request) => {
            if (request.actions.includes("reset")) {
                return;
            }

            if (request.actions.includes("start")) {
                this.showLoading(
                    this.selectedDocumentLabel(),
                    "The Document OCR Bot is reading the document ...",
                );
                document.getElementById("welcome")?.remove();
                return;
            }

            if (request.actions.includes("submit")) {
                const input = document.getElementById("chat-message");
                this.showLoading(
                    input?.value || this.component.getData("message"),
                    "The Document OCR Bot is preparing a response ...",
                );
                if (input) {
                    input.value = "";
                }
            }
        });

        this.component.on("loading.state:finished", () => {
            document.getElementById("loading-message")?.setAttribute("class", "d-none");
        });

        this.component.on("render:finished", () => {
            this.scrollToBottom();
        });
    }

    showLoading(userText, botText) {
        const loadingMessage = document.getElementById("loading-message");
        if (!loadingMessage) {
            return;
        }

        const userMessage = loadingMessage.getElementsByClassName("user-message")[0];
        if (userMessage) {
            userMessage.textContent = userText || "Document";
        }

        const botMessage = loadingMessage.getElementsByClassName("bot-message")[0];
        const botTextElement = botMessage?.querySelector("i");
        if (botTextElement) {
            botTextElement.textContent = botText;
        }

        loadingMessage.removeAttribute("class");
        this.scrollToBottom();
    }

    selectedDocumentLabel() {
        if (this.component.getData("sample") === "__own__") {
            return document.getElementById("document-url")?.value || this.component.getData("url") || "Document URL";
        }

        const select = document.getElementById("document-sample");

        return select?.selectedOptions[0]?.textContent || "Selected document";
    }

    scrollToBottom() {
        const chatBody = document.getElementById("chat-body");
        if (chatBody) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    }
}
