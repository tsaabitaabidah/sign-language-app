/**
 * MediaPipe Hand Detection Service
 * Handles hand tracking and gesture recognition using MediaPipe
 */

class MediaPipeService {
    constructor() {
        this.camera = null;
        this.hands = null;
        this.videoElement = null;
        this.canvasElement = null;
        this.canvasCtx = null;
        this.isDetecting = false;
        this.detectionCallback = null;
        this.lastDetectionTime = 0;
        this.detectionThrottle = 100; // ms between detections
        
        // Configuration
        this.config = {
            maxNumHands: 2,
            modelComplexity: 1,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        };
    }

    /**
     * Initialize MediaPipe Hands
     */
    async initialize(videoElement, canvasElement, options = {}) {
        try {
            this.videoElement = videoElement;
            this.canvasElement = canvasElement;
            this.canvasCtx = canvasElement.getContext('2d');
            
            // Merge custom options
            this.config = { ...this.config, ...options };

            // Setup camera
            this.camera = new Camera(videoElement, {
                onFrame: async () => {
                    if (this.hands && this.isDetecting) {
                        await this.hands.send({ image: videoElement });
                    }
                },
                width: 640,
                height: 480
            });

            // Setup MediaPipe Hands
            this.hands = new Hands({
                locateFile: (file) => {
                    return `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`;
                }
            });

            this.hands.setOptions({
                maxNumHands: this.config.maxNumHands,
                modelComplexity: this.config.modelComplexity,
                minDetectionConfidence: this.config.minDetectionConfidence,
                minTrackingConfidence: this.config.minTrackingConfidence
            });

            this.hands.onResults(this.onResults.bind(this));

            console.log('MediaPipe Hands initialized successfully');
            return true;
        } catch (error) {
            console.error('Failed to initialize MediaPipe:', error);
            throw error;
        }
    }

    /**
     * Start camera and detection
     */
    async start() {
        try {
            await this.camera.start();
            this.isDetecting = true;
            console.log('Camera started and detection enabled');
            return true;
        } catch (error) {
            console.error('Failed to start camera:', error);
            throw error;
        }
    }

    /**
     * Stop camera and detection
     */
    async stop() {
        try {
            this.isDetecting = false;
            if (this.camera) {
                await this.camera.stop();
            }
            console.log('Camera stopped and detection disabled');
            return true;
        } catch (error) {
            console.error('Failed to stop camera:', error);
            throw error;
        }
    }

    /**
     * Set detection callback function
     */
    setDetectionCallback(callback) {
        this.detectionCallback = callback;
    }

    /**
     * Handle MediaPipe results
     */
    async onResults(results) {
        try {
            // Clear canvas
            this.canvasCtx.save();
            this.canvasCtx.clearRect(0, 0, this.canvasElement.width, this.canvasElement.height);
            
            // Draw the video frame
            this.canvasCtx.drawImage(results.image, 0, 0, this.canvasElement.width, this.canvasElement.height);

            if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
                // Process detected hands
                const handsData = this.processHandResults(results);
                
                // Draw hand landmarks
                this.drawHandLandmarks(results.multiHandLandmarks, results.multiHandedness);
                
                // Throttle detection calls
                const now = Date.now();
                if (now - this.lastDetectionTime > this.detectionThrottle) {
                    this.lastDetectionTime = now;
                    
                    // Call detection callback if set
                    if (this.detectionCallback) {
                        this.detectionCallback(handsData);
                    }
                }
            } else {
                // No hands detected
                if (this.detectionCallback) {
                    this.detectionCallback(null);
                }
            }

            this.canvasCtx.restore();
        } catch (error) {
            console.error('Error processing MediaPipe results:', error);
        }
    }

    /**
     * Process hand detection results
     */
    processHandResults(results) {
        const handsData = {
            hands: [],
            handCount: results.multiHandLandmarks.length,
            timestamp: Date.now()
        };

        results.multiHandLandmarks.forEach((landmarks, index) => {
            const handedness = results.multiHandedness[index];
            const handData = {
                landmarks: this.normalizeLandmarks(landmarks),
                handedness: handedness.label, // 'Left' or 'Right'
                confidence: handedness.score,
                boundingBox: this.calculateBoundingBox(landmarks)
            };

            handsData.hands.push(handData);
        });

        return handsData;
    }

    /**
     * Normalize landmarks for consistent comparison
     */
    normalizeLandmarks(landmarks) {
        if (!landmarks || landmarks.length === 0) return [];

        // Use wrist (landmark 0) as reference point
        const wrist = landmarks[0];
        
        // Calculate scale based on distance from wrist to middle finger tip
        const middleTip = landmarks[12];
        const scale = Math.sqrt(
            Math.pow(middleTip.x - wrist.x, 2) +
            Math.pow(middleTip.y - wrist.y, 2) +
            Math.pow(middleTip.z - wrist.z, 2)
        );

        // Normalize each landmark relative to wrist and scale
        const normalized = landmarks.map(landmark => ({
            x: (landmark.x - wrist.x) / scale,
            y: (landmark.y - wrist.y) / scale,
            z: (landmark.z - wrist.z) / scale
        }));

        return normalized;
    }

    /**
     * Calculate bounding box for hand
     */
    calculateBoundingBox(landmarks) {
        if (!landmarks || landmarks.length === 0) return null;

        let minX = 1, minY = 1, maxX = 0, maxY = 0;
        
        landmarks.forEach(landmark => {
            minX = Math.min(minX, landmark.x);
            minY = Math.min(minY, landmark.y);
            maxX = Math.max(maxX, landmark.x);
            maxY = Math.max(maxY, landmark.y);
        });

        return {
            x: minX,
            y: minY,
            width: maxX - minX,
            height: maxY - minY
        };
    }

    /**
     * Draw hand landmarks on canvas
     */
    drawHandLandmarks(landmarks, handedness) {
        if (!landmarks || landmarks.length === 0) return;

        landmarks.forEach((handLandmarks, index) => {
            const isLeftHand = handedness[index]?.label === 'Left';
            
            // Set color based on handedness
            this.canvasCtx.fillStyle = isLeftHand ? '#FF6B6B' : '#4ECDC4';
            this.canvasCtx.strokeStyle = isLeftHand ? '#FF6B6B' : '#4ECDC4';
            
            // Draw connections
            this.drawConnectors(handLandmarks, HAND_CONNECTIONS);
            
            // Draw landmarks
            this.drawLandmarks(handLandmarks);
        });
    }

    /**
     * Draw connections between landmarks
     */
    drawConnectors(landmarks, connections) {
        this.canvasCtx.lineWidth = 2;
        
        connections.forEach(([start, end]) => {
            const startPoint = landmarks[start];
            const endPoint = landmarks[end];
            
            if (startPoint && endPoint) {
                this.canvasCtx.beginPath();
                this.canvasCtx.moveTo(
                    startPoint.x * this.canvasElement.width,
                    startPoint.y * this.canvasElement.height
                );
                this.canvasCtx.lineTo(
                    endPoint.x * this.canvasElement.width,
                    endPoint.y * this.canvasElement.height
                );
                this.canvasCtx.stroke();
            }
        });
    }

    /**
     * Draw individual landmarks
     */
    drawLandmarks(landmarks) {
        landmarks.forEach((landmark, index) => {
            const x = landmark.x * this.canvasElement.width;
            const y = landmark.y * this.canvasElement.height;
            
            // Draw point
            this.canvasCtx.beginPath();
            this.canvasCtx.arc(x, y, 5, 0, 2 * Math.PI);
            this.canvasCtx.fill();
            
            // Draw landmark number for debugging
            this.canvasCtx.fillStyle = 'white';
            this.canvasCtx.font = '10px Arial';
            this.canvasCtx.textAlign = 'center';
            this.canvasCtx.fillText(index.toString(), x, y - 8);
            this.canvasCtx.fillStyle = this.canvasCtx.strokeStyle; // Restore color
        });
    }

    /**
     * Get gesture data for API call
     */
    prepareGestureData(handsData) {
        if (!handsData || handsData.hands.length === 0) return null;

        // For single hand detection, use the first hand
        // For dual hand, we might need different logic
        const primaryHand = handsData.hands[0];
        
        return {
            landmarkData: primaryHand.landmarks,
            confidence: primaryHand.confidence,
            handCount: handsData.handCount,
            handData: handsData.hands.map(hand => ({
                landmarks: hand.landmarks,
                handedness: hand.handedness,
                confidence: hand.confidence
            }))
        };
    }

    /**
     * Check if hand is in valid position for detection
     */
    isValidHandPosition(handData) {
        if (!handData || !handData.boundingBox) return false;

        const { x, y, width, height } = handData.boundingBox;
        
        // Check if hand is reasonably sized and positioned
        const minSize = 0.1; // Minimum 10% of frame
        const maxSize = 0.8; // Maximum 80% of frame
        const minPosition = 0.1; // At least 10% from edges
        const maxPosition = 0.9; // At most 90% from edges
        
        const isSizeValid = width > minSize && width < maxSize && height > minSize && height < maxSize;
        const isPositionValid = x > minPosition && y > minPosition && 
                               (x + width) < maxPosition && (y + height) < maxPosition;
        
        return isSizeValid && isPositionValid;
    }

    /**
     * Get detection statistics
     */
    getStatistics() {
        return {
            isDetecting: this.isDetecting,
            detectionThrottle: this.detectionThrottle,
            lastDetectionTime: this.lastDetectionTime,
            config: this.config
        };
    }

    /**
     * Update configuration
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
        
        if (this.hands) {
            this.hands.setOptions({
                maxNumHands: this.config.maxNumHands,
                modelComplexity: this.config.modelComplexity,
                minDetectionConfidence: this.config.minDetectionConfidence,
                minTrackingConfidence: this.config.minTrackingConfidence
            });
        }
    }
}

// Hand connections for drawing
const HAND_CONNECTIONS = [
    [0, 1], [1, 2], [2, 3], [3, 4],     // Thumb
    [0, 5], [5, 6], [6, 7], [7, 8],     // Index finger
    [0, 9], [9, 10], [10, 11], [11, 12], // Middle finger
    [0, 13], [13, 14], [14, 15], [15, 16], // Ring finger
    [0, 17], [17, 18], [18, 19], [19, 20], // Pinky
    [5, 9], [9, 13], [13, 17]            // Palm connections
];

export default MediaPipeService;
